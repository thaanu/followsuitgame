<?php

namespace App\Game\Services;

use App\Enums\AceRule;
use App\Enums\CardRank;
use App\Enums\CardSuit;
use App\Game\Data\RuleConfiguration;
use App\Game\Support\CardCode;

/**
 * Builds and shuffles decks.
 *
 * Shuffling uses `random_int`, which is cryptographically secure. Tests inject a
 * fixed order through {@see self::orderBy()} instead of stubbing randomness.
 *
 * @phpstan-type DeckCard array{deck_number: int, suit: CardSuit, rank: CardRank, rank_value: int, card_code: string}
 */
class DeckService
{
    /**
     * Build an unshuffled deck for the given rules.
     *
     * @return array<int, DeckCard>
     */
    public function build(RuleConfiguration $rules, int $deckCount = 1): array
    {
        $cards = [];

        for ($deckNumber = 1; $deckNumber <= $deckCount; $deckNumber++) {
            foreach (CardSuit::standard() as $suit) {
                foreach (CardRank::standard() as $rank) {
                    $cards[] = [
                        'deck_number' => $deckNumber,
                        'suit' => $suit,
                        'rank' => $rank,
                        'rank_value' => $this->rankValue($rank, $rules->aceRule),
                        'card_code' => CardCode::make($rank, $suit, $deckNumber),
                    ];
                }
            }

            for ($jokerIndex = 1; $jokerIndex <= $rules->deckType->jokerCount(); $jokerIndex++) {
                $cards[] = [
                    'deck_number' => $deckNumber,
                    'suit' => CardSuit::None,
                    'rank' => CardRank::Joker,
                    'rank_value' => 0,
                    'card_code' => CardCode::make(CardRank::Joker, CardSuit::None, $deckNumber, $jokerIndex),
                ];
            }
        }

        return $cards;
    }

    /**
     * Build and shuffle a deck.
     *
     * @return array<int, DeckCard>
     */
    public function buildShuffled(RuleConfiguration $rules, int $deckCount = 1): array
    {
        return $this->shuffle($this->build($rules, $deckCount));
    }

    /**
     * Fisher-Yates shuffle backed by a cryptographically secure random source.
     *
     * @template TCard
     *
     * @param  array<int, TCard>  $cards
     * @return array<int, TCard>
     */
    public function shuffle(array $cards): array
    {
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }

        return $cards;
    }

    /**
     * Reorder a deck so it starts with the given card codes, in that exact order.
     *
     * Deterministic tests use this instead of relying on the shuffle.
     *
     * @param  array<int, DeckCard>  $cards
     * @param  array<int, string>  $codes
     * @return array<int, DeckCard>
     */
    public function orderBy(array $cards, array $codes): array
    {
        $byCode = [];
        foreach ($cards as $card) {
            $byCode[$card['card_code']] = $card;
        }

        $ordered = [];
        foreach ($codes as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
                unset($byCode[$code]);
            }
        }

        return [...$ordered, ...array_values($byCode)];
    }

    private function rankValue(CardRank $rank, AceRule $aceRule): int
    {
        if ($aceRule === AceRule::RankIrrelevant) {
            return $rank->value(AceRule::AceLow);
        }

        return $rank->value($aceRule);
    }
}
