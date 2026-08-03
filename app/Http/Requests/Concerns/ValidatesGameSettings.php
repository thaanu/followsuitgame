<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AceRule;
use App\Enums\CardRank;
use App\Enums\CollectedCardsDestination;
use App\Enums\DealMethod;
use App\Enums\DeckType;
use App\Enums\DisconnectBehaviour;
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
use App\Game\Support\CardCode;
use Illuminate\Validation\Rule;

/**
 * The validation rules shared by game creation and settings updates.
 *
 * Every value is checked against its enum, so an unknown rule value can never reach
 * the rules JSON.
 */
trait ValidatesGameSettings
{
    /**
     * @return array<string, mixed>
     */
    protected function settingsRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:60'],
            'maximum_players' => ['required', 'integer', 'min:2', 'max:4'],
            'rule_preset' => ['required', Rule::enum(RulePreset::class)],

            'rules' => ['required', 'array'],

            'rules.general.deck_type' => ['required', Rule::enum(DeckType::class)],
            'rules.general.deal_method' => ['required', Rule::enum(DealMethod::class)],
            'rules.general.fixed_cards_per_player' => ['required', 'integer', 'min:1', 'max:13'],
            'rules.general.starting_player_method' => ['required', Rule::enum(StartingPlayerMethod::class)],
            'rules.general.starting_card_code' => [
                'required_if:rules.general.starting_player_method,'.StartingPlayerMethod::SpecificCard->value,
                'nullable',
                'string',
                Rule::in(array_column(CardCode::selectableCards(), 'code')),
            ],
            'rules.general.turn_direction' => ['required', Rule::enum(TurnDirection::class)],

            'rules.follow_suit.unable_to_follow_action' => ['required', Rule::enum(UnableToFollowAction::class)],
            'rules.follow_suit.draw_card_count' => ['required', 'integer', 'min:1', 'max:5'],
            'rules.follow_suit.allow_voluntary_collect' => ['required', 'boolean'],
            'rules.follow_suit.next_lead_rule' => ['required', Rule::enum(NextLeadRule::class)],

            'rules.pile.end_condition' => ['required', Rule::enum(PileEndCondition::class)],
            'rules.pile.fixed_card_count' => ['required', 'integer', 'min:2', 'max:20'],
            'rules.pile.collected_cards_destination' => ['required', Rule::enum(CollectedCardsDestination::class)],

            'rules.ranking.ace_rule' => ['required', Rule::enum(AceRule::class)],
            'rules.ranking.round_winner' => ['required', Rule::enum(RoundWinner::class)],

            'rules.special_cards.enabled' => ['required', 'boolean'],
            'rules.special_cards.effects' => ['required', 'array'],
            'rules.special_cards.effects.*' => ['boolean'],
            'rules.special_cards.clear_pile_rank' => ['nullable', Rule::enum(CardRank::class)],

            'rules.winning.condition' => ['required', Rule::enum(WinningCondition::class)],
            'rules.winning.round_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'rules.winning.tie_break' => ['required', Rule::enum(TieBreakRule::class)],

            'rules.timer.enabled' => ['required', 'boolean'],
            'rules.timer.seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'rules.timer.timeout_action' => ['required', Rule::enum(TimeoutAction::class)],

            'rules.connection.grace_period_seconds' => ['required', 'integer', 'min:10', 'max:600'],
            'rules.connection.disconnect_behaviour' => ['required', Rule::enum(DisconnectBehaviour::class)],

            'punishment_enabled' => ['required', 'boolean'],
            'punishment.title' => ['required_if:punishment_enabled,true', 'nullable', 'string', 'max:80'],
            'punishment.description' => ['nullable', 'string', 'max:400'],
            'punishment.emoji' => ['nullable', 'string', 'max:8'],
            'punishment.severity' => ['nullable', Rule::enum(PunishmentSeverity::class)],
            'punishment.target' => ['required_if:punishment_enabled,true', 'nullable', Rule::enum(PunishmentTarget::class)],
            'punishment.agreement_confirmed' => ['accepted_if:punishment_enabled,true', 'nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function settingsMessages(): array
    {
        return [
            'punishment.agreement_confirmed.accepted_if' => 'You must confirm that all players should agree to the punishment.',
            'punishment.title.required_if' => 'Give the punishment a title, or turn the punishment off.',
            'rules.general.starting_card_code.required_if' => 'Choose which card decides who starts.',
        ];
    }

    /**
     * The validated rules array, hydrated through the value object so unknown keys
     * are dropped and missing keys fall back to the defaults.
     *
     * @return array<string, mixed>
     */
    public function ruleConfigurationArray(): array
    {
        /** @var array<string, mixed> $rules */
        $rules = $this->validated('rules', []);

        // A named preset always wins over whatever the form posted, so a preset game
        // cannot be nudged off its published rules.
        $preset = RulePreset::from((string) $this->validated('rule_preset'));

        if ($preset !== RulePreset::Custom) {
            return RuleConfiguration::presetRules($preset);
        }

        return RuleConfiguration::fromArray($rules)->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function punishmentArray(): ?array
    {
        if (! $this->boolean('punishment_enabled')) {
            return null;
        }

        /** @var array<string, mixed> $punishment */
        $punishment = $this->validated('punishment', []);

        return [
            'title' => strip_tags((string) ($punishment['title'] ?? '')),
            'description' => strip_tags((string) ($punishment['description'] ?? '')),
            'emoji' => $punishment['emoji'] ?? null,
            'severity' => $punishment['severity'] ?? null,
            'target' => $punishment['target'] ?? PunishmentTarget::MostCardsCollected->value,
            'agreement_confirmed' => true,
        ];
    }

    /**
     * Normalise the special card effect map so every case is present as a boolean.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normaliseEffects(array $input): array
    {
        $effects = [];

        foreach (SpecialCardEffect::cases() as $effect) {
            $effects[$effect->value] = filter_var(
                data_get($input, "rules.special_cards.effects.{$effect->value}", false),
                FILTER_VALIDATE_BOOLEAN,
            );
        }

        data_set($input, 'rules.special_cards.effects', $effects);

        return $input;
    }
}
