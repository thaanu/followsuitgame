<?php

use App\Enums\CardRank;
use App\Enums\CardSuit;
use App\Enums\DeckType;
use App\Enums\RulePreset;
use App\Game\Data\RuleConfiguration;
use App\Game\Services\DeckService;
use App\Game\Support\CardCode;

it('builds a 52 card deck with unique codes', function () {
    $deck = app(DeckService::class)->build(RuleConfiguration::preset(RulePreset::Classic));

    expect($deck)->toHaveCount(52)
        ->and(array_unique(array_column($deck, 'card_code')))->toHaveCount(52);
});

it('adds two jokers for the joker deck', function () {
    $rules = RuleConfiguration::fromArray(['general' => ['deck_type' => DeckType::Standard54WithJokers->value]]);

    $deck = app(DeckService::class)->build($rules);

    expect($deck)->toHaveCount(54)
        ->and(array_filter($deck, fn ($card) => $card['rank'] === CardRank::Joker))->toHaveCount(2);
});

it('values the ace by the configured rule', function () {
    $high = app(DeckService::class)->build(RuleConfiguration::fromArray(['ranking' => ['ace_rule' => 'ace_high']]));
    $low = app(DeckService::class)->build(RuleConfiguration::fromArray(['ranking' => ['ace_rule' => 'ace_low']]));

    $aceOf = fn (array $deck) => collect($deck)->firstWhere('card_code', 'AS')['rank_value'];

    expect($aceOf($high))->toBe(14)->and($aceOf($low))->toBe(1);
});

it('keeps every card when shuffling', function () {
    $service = app(DeckService::class);
    $deck = $service->build(RuleConfiguration::preset(RulePreset::Classic));

    $shuffled = $service->shuffle($deck);

    expect(array_column($shuffled, 'card_code'))
        ->toEqualCanonicalizing(array_column($deck, 'card_code'));
});

it('puts the requested cards on top when an order is forced', function () {
    $service = app(DeckService::class);
    $deck = $service->build(RuleConfiguration::preset(RulePreset::Classic));

    $ordered = $service->orderBy($deck, ['KS', 'AH', '7D']);

    expect(array_slice(array_column($ordered, 'card_code'), 0, 3))->toBe(['KS', 'AH', '7D'])
        ->and($ordered)->toHaveCount(52);
});

it('parses and formats card codes', function () {
    expect(CardCode::make(CardRank::Ace, CardSuit::Spades))->toBe('AS')
        ->and(CardCode::parse('10H'))->toBe(['rank' => CardRank::Ten, 'suit' => CardSuit::Hearts])
        ->and(CardCode::parse('JOKER-1'))->toBeNull()
        ->and(CardCode::parse('nonsense'))->toBeNull();
});

it('offers every standard card as a starting-card choice', function () {
    expect(CardCode::selectableCards())->toHaveCount(52);
});
