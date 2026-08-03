<?php

namespace App\Http\Controllers;

use App\Enums\AceRule;
use App\Enums\CardRank;
use App\Enums\CollectedCardsDestination;
use App\Enums\DealMethod;
use App\Enums\DeckType;
use App\Enums\DisconnectBehaviour;
use App\Enums\GameActionType;
use App\Enums\GameStatus;
use App\Enums\NextLeadRule;
use App\Enums\PileEndCondition;
use App\Enums\PunishmentSeverity;
use App\Enums\PunishmentTarget;
use App\Enums\RoundWinner;
use App\Enums\RulePreset;
use App\Enums\SpecialCardEffect;
use App\Enums\StartingPlayerMethod;
use App\Enums\TieBreakRule;
use App\Enums\TimeoutAction;
use App\Enums\TurnDirection;
use App\Enums\UnableToFollowAction;
use App\Enums\WinningCondition;
use App\Game\Data\RuleConfiguration;
use App\Game\Services\ActivityLogger;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\GameCreationService;
use App\Game\Services\ReconnectionService;
use App\Game\Support\ActivityMessage;
use App\Game\Support\CardCode;
use App\Http\Controllers\Concerns\InteractsWithGamePlayer;
use App\Http\Requests\StoreGameRequest;
use App\Models\Game;
use App\Models\RulePresetRecord;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    use InteractsWithGamePlayer;

    public function __construct(
        private readonly GameCreationService $creation,
        private readonly ReconnectionService $reconnection,
        private readonly GameBroadcaster $broadcaster,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * The create-a-game screen, with every rule option the host can pick from.
     */
    public function create(): Response
    {
        return Inertia::render('Games/Create', [
            'options' => self::ruleOptions(),
            'presets' => self::presets(),
            'defaults' => RuleConfiguration::presetRules(RulePreset::Classic),
            'punishmentNotice' => config('followsuit.punishment_notice'),
            'punishmentExamples' => self::punishmentExamples(),
        ]);
    }

    public function store(StoreGameRequest $request): RedirectResponse
    {
        ['game' => $game, 'token' => $token] = $this->creation->create(
            hostNickname: (string) $request->validated('nickname'),
            maximumPlayers: (int) $request->validated('maximum_players'),
            name: $request->validated('name'),
            preset: RulePreset::from((string) $request->validated('rule_preset')),
            rules: $request->ruleConfigurationArray(),
            punishment: $request->punishmentArray(),
        );

        $this->reconnection->remember($request, $game, $token);

        return to_route('games.lobby', $game)->with('playerToken', $token);
    }

    /**
     * The host cancelling the game for everybody.
     */
    public function cancel(Request $request, Game $game): RedirectResponse
    {
        $this->authorizePlayer($request, 'cancel', $game);

        if (! $game->status->isFinished()) {
            $game->forceFill([
                'status' => GameStatus::Cancelled,
                'cancelled_at' => now(),
                'current_player_id' => null,
                'turn_deadline_at' => null,
                'state_version' => $game->state_version + 1,
            ])->save();

            $this->activity->log(
                game: $game,
                type: GameActionType::GameCancelled,
                message: ActivityMessage::gameCancelled(),
            );

            $this->broadcaster->gameCancelled($game);
        }

        return to_route('home');
    }

    /**
     * Every selectable value for the rule form, as label/value pairs.
     *
     * @return array<string, mixed>
     */
    public static function ruleOptions(): array
    {
        return [
            'deck_types' => self::enumOptions(DeckType::cases()),
            'deal_methods' => self::enumOptions(DealMethod::cases()),
            'starting_player_methods' => self::enumOptions(StartingPlayerMethod::cases()),
            'turn_directions' => self::enumOptions(TurnDirection::cases()),
            'unable_to_follow_actions' => self::enumOptions(UnableToFollowAction::cases()),
            'next_lead_rules' => self::enumOptions(NextLeadRule::cases()),
            'pile_end_conditions' => self::enumOptions(PileEndCondition::cases()),
            'collected_cards_destinations' => self::enumOptions(CollectedCardsDestination::cases()),
            'ace_rules' => self::enumOptions(AceRule::cases()),
            'round_winners' => self::enumOptions(RoundWinner::cases()),
            'winning_conditions' => self::enumOptions(WinningCondition::cases()),
            'tie_break_rules' => self::enumOptions(TieBreakRule::cases()),
            'timeout_actions' => self::enumOptions(TimeoutAction::cases()),
            'disconnect_behaviours' => self::enumOptions(DisconnectBehaviour::cases()),
            'punishment_targets' => self::enumOptions(PunishmentTarget::cases()),
            'punishment_severities' => self::enumOptions(PunishmentSeverity::cases()),
            'special_card_effects' => self::enumOptions(SpecialCardEffect::cases()),
            'clear_pile_ranks' => array_map(
                fn ($rank) => ['value' => $rank->value, 'label' => $rank->label()],
                CardRank::standard(),
            ),
            'cards' => CardCode::selectableCards(),
            'timer_presets' => [15, 30, 45, 60],
        ];
    }

    /**
     * The presets offered on the create screen.
     *
     * Built-in presets live in the `rule_presets` table so hosts can save their own
     * later; the enum is the fallback when the table has not been seeded.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function presets(): array
    {
        $saved = RulePresetRecord::orderBy('sort_order')->get()
            ->map(fn (RulePresetRecord $record) => [
                'value' => $record->slug,
                'label' => $record->name,
                'description' => (string) $record->description,
                'rules' => $record->configuration()->toArray(),
            ])
            ->all();

        if ($saved === []) {
            $saved = array_map(fn (RulePreset $preset) => [
                'value' => $preset->value,
                'label' => $preset->label(),
                'description' => $preset->description(),
                'rules' => RuleConfiguration::presetRules($preset),
            ], array_filter(RulePreset::cases(), fn (RulePreset $preset) => $preset !== RulePreset::Custom));
        }

        return [
            ...array_values($saved),
            [
                'value' => RulePreset::Custom->value,
                'label' => RulePreset::Custom->label(),
                'description' => RulePreset::Custom->description(),
                'rules' => RuleConfiguration::presetRules(RulePreset::Custom),
            ],
        ];
    }

    /**
     * Safe, mutually agreeable suggestions offered next to the punishment form.
     *
     * @return array<int, array{title: string, emoji: string}>
     */
    public static function punishmentExamples(): array
    {
        return [
            ['title' => 'Buy snacks for everyone', 'emoji' => '🍿'],
            ['title' => 'Sing a song', 'emoji' => '🎤'],
            ['title' => 'Do ten push-ups', 'emoji' => '💪'],
            ['title' => 'Make tea for the group', 'emoji' => '🫖'],
            ['title' => 'Post a funny status', 'emoji' => '😄'],
            ['title' => 'Pay for the next game-night refreshments', 'emoji' => '🥤'],
            ['title' => 'Do the washing up', 'emoji' => '🧽'],
            ['title' => 'Pick the next game', 'emoji' => '🎲'],
        ];
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private static function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case) => [
            'value' => (string) $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
        ], $cases);
    }
}
