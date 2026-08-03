<?php

namespace App\Game\Data;

use App\Enums\AceRule;
use App\Enums\CardRank;
use App\Enums\CollectedCardsDestination;
use App\Enums\DealMethod;
use App\Enums\DeckType;
use App\Enums\DisconnectBehaviour;
use App\Enums\NextLeadRule;
use App\Enums\PileEndCondition;
use App\Enums\RoundWinner;
use App\Enums\RulePreset;
use App\Enums\SpecialCardEffect;
use App\Enums\StartingPlayerMethod;
use App\Enums\TieBreakRule;
use App\Enums\TimeoutAction;
use App\Enums\TurnDirection;
use App\Enums\UnableToFollowAction;
use App\Enums\WinningCondition;
use Illuminate\Support\Arr;

/**
 * An immutable, fully typed view over the rules JSON stored on a game.
 *
 * Unknown keys are discarded during hydration and missing keys fall back to the
 * defaults, so rule documents written by an older release keep working.
 *
 * @phpstan-type SpecialEffectMap array<string, bool>
 */
final readonly class RuleConfiguration
{
    /**
     * @param  array<string, bool>  $specialCardEffects  keyed by SpecialCardEffect value
     */
    public function __construct(
        public DeckType $deckType,
        public DealMethod $dealMethod,
        public int $fixedCardsPerPlayer,
        public StartingPlayerMethod $startingPlayerMethod,
        public string $startingCardCode,
        public TurnDirection $turnDirection,
        public UnableToFollowAction $unableToFollowAction,
        public int $drawCardCount,
        public bool $allowVoluntaryCollect,
        public NextLeadRule $nextLeadRule,
        public PileEndCondition $pileEndCondition,
        public int $pileFixedCardCount,
        public CollectedCardsDestination $collectedCardsDestination,
        public AceRule $aceRule,
        public RoundWinner $roundWinner,
        public bool $specialCardsEnabled,
        public array $specialCardEffects,
        public ?CardRank $clearPileRank,
        public WinningCondition $winningCondition,
        public int $roundLimit,
        public TieBreakRule $tieBreakRule,
        public bool $timerEnabled,
        public int $timerSeconds,
        public TimeoutAction $timeoutAction,
        public int $reconnectionGraceSeconds,
        public DisconnectBehaviour $disconnectBehaviour,
    ) {}

    /**
     * The default rule document, which is also the Classic Follow Suit preset.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'general' => [
                'deck_type' => DeckType::Standard52->value,
                'deal_method' => DealMethod::AllEvenly->value,
                'fixed_cards_per_player' => 7,
                'starting_player_method' => StartingPlayerMethod::Host->value,
                'starting_card_code' => 'AS',
                'turn_direction' => TurnDirection::Clockwise->value,
            ],
            'follow_suit' => [
                'unable_to_follow_action' => UnableToFollowAction::CollectPile->value,
                'draw_card_count' => 1,
                'allow_voluntary_collect' => false,
                'next_lead_rule' => NextLeadRule::Collector->value,
            ],
            'pile' => [
                'end_condition' => PileEndCondition::CannotFollowSuit->value,
                'fixed_card_count' => 4,
                'collected_cards_destination' => CollectedCardsDestination::Hand->value,
            ],
            'ranking' => [
                'ace_rule' => AceRule::AceHigh->value,
                'round_winner' => RoundWinner::HighestCard->value,
            ],
            'special_cards' => [
                'enabled' => false,
                'effects' => [
                    SpecialCardEffect::AceChangesSuit->value => false,
                    SpecialCardEffect::KingReversesDirection->value => false,
                    SpecialCardEffect::QueenSkipsNext->value => false,
                    SpecialCardEffect::JackExtraTurn->value => false,
                    SpecialCardEffect::JokerWildSuit->value => false,
                    SpecialCardEffect::ClearPile->value => false,
                ],
                'clear_pile_rank' => null,
            ],
            'winning' => [
                'condition' => WinningCondition::FirstToEmptyHand->value,
                'round_limit' => 3,
                'tie_break' => TieBreakRule::FewerCollectedCards->value,
            ],
            'timer' => [
                'enabled' => false,
                'seconds' => 30,
                'timeout_action' => TimeoutAction::PlayRandomLegal->value,
            ],
            'connection' => [
                'grace_period_seconds' => 60,
                'disconnect_behaviour' => DisconnectBehaviour::SkipAfterTimeout->value,
            ],
        ];
    }

    /**
     * The rule document for a named preset.
     *
     * @return array<string, mixed>
     */
    public static function presetRules(RulePreset $preset): array
    {
        $rules = self::defaults();

        return match ($preset) {
            RulePreset::Classic, RulePreset::Custom => $rules,
            RulePreset::Quick => array_replace_recursive($rules, [
                'general' => [
                    'deal_method' => DealMethod::FixedCount->value,
                    'fixed_cards_per_player' => 7,
                ],
                'pile' => [
                    'collected_cards_destination' => CollectedCardsDestination::CountOnly->value,
                ],
                'winning' => [
                    'condition' => WinningCondition::FewestCollectedCards->value,
                    'tie_break' => TieBreakRule::FewerCollectedPiles->value,
                ],
            ]),
        };
    }

    public static function preset(RulePreset $preset): self
    {
        return self::fromArray(self::presetRules($preset));
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public static function fromArray(array $rules): self
    {
        $merged = array_replace_recursive(self::defaults(), $rules);

        $effects = [];
        foreach (SpecialCardEffect::cases() as $effect) {
            $effects[$effect->value] = (bool) Arr::get($merged, "special_cards.effects.{$effect->value}", false);
        }

        return new self(
            deckType: self::enum(DeckType::class, Arr::get($merged, 'general.deck_type'), DeckType::Standard52),
            dealMethod: self::enum(DealMethod::class, Arr::get($merged, 'general.deal_method'), DealMethod::AllEvenly),
            fixedCardsPerPlayer: max(1, (int) Arr::get($merged, 'general.fixed_cards_per_player', 7)),
            startingPlayerMethod: self::enum(StartingPlayerMethod::class, Arr::get($merged, 'general.starting_player_method'), StartingPlayerMethod::Host),
            startingCardCode: (string) Arr::get($merged, 'general.starting_card_code', 'AS'),
            turnDirection: self::enum(TurnDirection::class, Arr::get($merged, 'general.turn_direction'), TurnDirection::Clockwise),
            unableToFollowAction: self::enum(UnableToFollowAction::class, Arr::get($merged, 'follow_suit.unable_to_follow_action'), UnableToFollowAction::CollectPile),
            drawCardCount: max(1, (int) Arr::get($merged, 'follow_suit.draw_card_count', 1)),
            allowVoluntaryCollect: (bool) Arr::get($merged, 'follow_suit.allow_voluntary_collect', false),
            nextLeadRule: self::enum(NextLeadRule::class, Arr::get($merged, 'follow_suit.next_lead_rule'), NextLeadRule::Collector),
            pileEndCondition: self::enum(PileEndCondition::class, Arr::get($merged, 'pile.end_condition'), PileEndCondition::CannotFollowSuit),
            pileFixedCardCount: max(2, (int) Arr::get($merged, 'pile.fixed_card_count', 4)),
            collectedCardsDestination: self::enum(CollectedCardsDestination::class, Arr::get($merged, 'pile.collected_cards_destination'), CollectedCardsDestination::Hand),
            aceRule: self::enum(AceRule::class, Arr::get($merged, 'ranking.ace_rule'), AceRule::AceHigh),
            roundWinner: self::enum(RoundWinner::class, Arr::get($merged, 'ranking.round_winner'), RoundWinner::HighestCard),
            specialCardsEnabled: (bool) Arr::get($merged, 'special_cards.enabled', false),
            specialCardEffects: $effects,
            clearPileRank: CardRank::tryFrom((string) Arr::get($merged, 'special_cards.clear_pile_rank', '')),
            winningCondition: self::enum(WinningCondition::class, Arr::get($merged, 'winning.condition'), WinningCondition::FirstToEmptyHand),
            roundLimit: max(1, (int) Arr::get($merged, 'winning.round_limit', 3)),
            tieBreakRule: self::enum(TieBreakRule::class, Arr::get($merged, 'winning.tie_break'), TieBreakRule::FewerCollectedCards),
            timerEnabled: (bool) Arr::get($merged, 'timer.enabled', false),
            timerSeconds: max(5, min(600, (int) Arr::get($merged, 'timer.seconds', 30))),
            timeoutAction: self::enum(TimeoutAction::class, Arr::get($merged, 'timer.timeout_action'), TimeoutAction::PlayRandomLegal),
            reconnectionGraceSeconds: max(10, min(600, (int) Arr::get($merged, 'connection.grace_period_seconds', 60))),
            disconnectBehaviour: self::enum(DisconnectBehaviour::class, Arr::get($merged, 'connection.disconnect_behaviour'), DisconnectBehaviour::SkipAfterTimeout),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'general' => [
                'deck_type' => $this->deckType->value,
                'deal_method' => $this->dealMethod->value,
                'fixed_cards_per_player' => $this->fixedCardsPerPlayer,
                'starting_player_method' => $this->startingPlayerMethod->value,
                'starting_card_code' => $this->startingCardCode,
                'turn_direction' => $this->turnDirection->value,
            ],
            'follow_suit' => [
                'unable_to_follow_action' => $this->unableToFollowAction->value,
                'draw_card_count' => $this->drawCardCount,
                'allow_voluntary_collect' => $this->allowVoluntaryCollect,
                'next_lead_rule' => $this->nextLeadRule->value,
            ],
            'pile' => [
                'end_condition' => $this->pileEndCondition->value,
                'fixed_card_count' => $this->pileFixedCardCount,
                'collected_cards_destination' => $this->collectedCardsDestination->value,
            ],
            'ranking' => [
                'ace_rule' => $this->aceRule->value,
                'round_winner' => $this->roundWinner->value,
            ],
            'special_cards' => [
                'enabled' => $this->specialCardsEnabled,
                'effects' => $this->specialCardEffects,
                'clear_pile_rank' => $this->clearPileRank?->value,
            ],
            'winning' => [
                'condition' => $this->winningCondition->value,
                'round_limit' => $this->roundLimit,
                'tie_break' => $this->tieBreakRule->value,
            ],
            'timer' => [
                'enabled' => $this->timerEnabled,
                'seconds' => $this->timerSeconds,
                'timeout_action' => $this->timeoutAction->value,
            ],
            'connection' => [
                'grace_period_seconds' => $this->reconnectionGraceSeconds,
                'disconnect_behaviour' => $this->disconnectBehaviour->value,
            ],
        ];
    }

    public function isEffectEnabled(SpecialCardEffect $effect): bool
    {
        if (! $this->specialCardsEnabled) {
            return false;
        }

        if ($effect === SpecialCardEffect::ClearPile && $this->clearPileRank === null) {
            return false;
        }

        return $this->specialCardEffects[$effect->value] ?? false;
    }

    /**
     * Every special card effect that is currently switched on.
     *
     * @return array<int, SpecialCardEffect>
     */
    public function enabledEffects(): array
    {
        return array_values(array_filter(
            SpecialCardEffect::cases(),
            fn (SpecialCardEffect $effect) => $this->isEffectEnabled($effect),
        ));
    }

    /**
     * A short, human readable summary of the rules for the lobby and table screens.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function summary(): array
    {
        $summary = [
            ['label' => 'Deck', 'value' => $this->deckType->label()],
            ['label' => 'Dealing', 'value' => $this->dealMethod === DealMethod::AllEvenly
                ? 'All cards dealt evenly'
                : "{$this->fixedCardsPerPlayer} cards each"],
            ['label' => 'Starting player', 'value' => $this->startingPlayerMethod === StartingPlayerMethod::SpecificCard
                ? "Holder of {$this->startingCardCode}"
                : $this->startingPlayerMethod->label()],
            ['label' => 'Turn direction', 'value' => $this->turnDirection->label()],
            ['label' => 'Cannot follow suit', 'value' => $this->unableToFollowAction === UnableToFollowAction::DrawCards
                ? "Draw {$this->drawCardCount} card(s)"
                : $this->unableToFollowAction->label()],
            ['label' => 'Pile ends', 'value' => $this->pileEndCondition === PileEndCondition::FixedCardCount
                ? "After {$this->pileFixedCardCount} cards"
                : $this->pileEndCondition->label()],
            ['label' => 'Collected cards', 'value' => $this->collectedCardsDestination->label()],
            ['label' => 'Next lead', 'value' => $this->nextLeadRule->label()],
            ['label' => 'Card ranking', 'value' => $this->aceRule->label()],
            ['label' => 'Winning', 'value' => $this->winningCondition === WinningCondition::FixedRounds
                ? "After {$this->roundLimit} round(s)"
                : $this->winningCondition->label()],
            ['label' => 'Tie break', 'value' => $this->tieBreakRule->label()],
            ['label' => 'Turn timer', 'value' => $this->timerEnabled
                ? "{$this->timerSeconds} seconds, then {$this->timeoutAction->label()}"
                : 'Disabled'],
            ['label' => 'Voluntary collect', 'value' => $this->allowVoluntaryCollect ? 'Allowed' : 'Not allowed'],
        ];

        $effects = $this->enabledEffects();

        $summary[] = [
            'label' => 'Special cards',
            'value' => $effects === []
                ? 'Disabled'
                : implode(', ', array_map(fn (SpecialCardEffect $effect) => $effect->label(), $effects)),
        ];

        return $summary;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @param  T  $fallback
     * @return T
     */
    private static function enum(string $enum, mixed $value, mixed $fallback): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $fallback;
        }

        return $enum::tryFrom($value) ?? $fallback;
    }
}
