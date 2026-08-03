<?php

use App\Enums\CollectedCardsDestination;
use App\Enums\DealMethod;
use App\Enums\RulePreset;
use App\Enums\SpecialCardEffect;
use App\Enums\WinningCondition;
use App\Game\Data\RuleConfiguration;

it('hydrates the classic preset', function () {
    $rules = RuleConfiguration::preset(RulePreset::Classic);

    expect($rules->dealMethod)->toBe(DealMethod::AllEvenly)
        ->and($rules->collectedCardsDestination)->toBe(CollectedCardsDestination::Hand)
        ->and($rules->winningCondition)->toBe(WinningCondition::FirstToEmptyHand);
});

it('hydrates the quick preset', function () {
    $rules = RuleConfiguration::preset(RulePreset::Quick);

    expect($rules->dealMethod)->toBe(DealMethod::FixedCount)
        ->and($rules->fixedCardsPerPlayer)->toBe(7)
        ->and($rules->collectedCardsDestination)->toBe(CollectedCardsDestination::CountOnly)
        ->and($rules->winningCondition)->toBe(WinningCondition::FewestCollectedCards);
});

it('survives a round trip through the array form', function () {
    $original = RuleConfiguration::preset(RulePreset::Quick);

    expect(RuleConfiguration::fromArray($original->toArray())->toArray())->toBe($original->toArray());
});

it('falls back to defaults for missing keys', function () {
    $rules = RuleConfiguration::fromArray(['winning' => ['condition' => 'fewest_collected_piles']]);

    expect($rules->winningCondition)->toBe(WinningCondition::FewestCollectedPiles)
        ->and($rules->dealMethod)->toBe(DealMethod::AllEvenly);
});

it('discards unknown keys and unknown enum values', function () {
    $rules = RuleConfiguration::fromArray([
        'nonsense' => ['key' => 'value'],
        'winning' => ['condition' => 'not_a_real_condition'],
    ]);

    expect($rules->winningCondition)->toBe(WinningCondition::FirstToEmptyHand)
        ->and($rules->toArray())->not->toHaveKey('nonsense');
});

it('clamps out-of-range numbers', function () {
    $rules = RuleConfiguration::fromArray([
        'timer' => ['seconds' => 99999],
        'follow_suit' => ['draw_card_count' => 0],
        'connection' => ['grace_period_seconds' => 1],
    ]);

    expect($rules->timerSeconds)->toBe(600)
        ->and($rules->drawCardCount)->toBe(1)
        ->and($rules->reconnectionGraceSeconds)->toBe(10);
});

it('reports an effect as off while special cards are disabled', function () {
    $rules = RuleConfiguration::fromArray([
        'special_cards' => ['enabled' => false, 'effects' => [SpecialCardEffect::AceChangesSuit->value => true]],
    ]);

    expect($rules->isEffectEnabled(SpecialCardEffect::AceChangesSuit))->toBeFalse()
        ->and($rules->enabledEffects())->toBeEmpty();
});

it('needs a rank before the clear-pile effect counts as enabled', function () {
    $withoutRank = RuleConfiguration::fromArray([
        'special_cards' => ['enabled' => true, 'effects' => [SpecialCardEffect::ClearPile->value => true]],
    ]);

    $withRank = RuleConfiguration::fromArray([
        'special_cards' => [
            'enabled' => true,
            'effects' => [SpecialCardEffect::ClearPile->value => true],
            'clear_pile_rank' => 'two',
        ],
    ]);

    expect($withoutRank->isEffectEnabled(SpecialCardEffect::ClearPile))->toBeFalse()
        ->and($withRank->isEffectEnabled(SpecialCardEffect::ClearPile))->toBeTrue();
});

it('produces a readable summary', function () {
    $summary = RuleConfiguration::preset(RulePreset::Classic)->summary();

    expect($summary)->not->toBeEmpty()
        ->and(array_column($summary, 'label'))->toContain('Deck', 'Winning', 'Turn timer');
});
