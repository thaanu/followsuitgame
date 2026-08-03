<?php

namespace App\Game\Support;

use App\Enums\CardRank;
use App\Enums\CardSuit;

/**
 * Parsing and formatting for the short card codes used in rules and the UI,
 * for example `AS` (Ace of Spades), `10H` or `JOKER-1`.
 */
final class CardCode
{
    public static function make(CardRank $rank, CardSuit $suit, int $deckNumber = 1, int $index = 1): string
    {
        if ($rank === CardRank::Joker) {
            return $deckNumber > 1
                ? "JOKER-{$deckNumber}-{$index}"
                : "JOKER-{$index}";
        }

        $code = $rank->code().$suit->code();

        return $deckNumber > 1 ? "{$code}#{$deckNumber}" : $code;
    }

    /**
     * @return array{rank: CardRank, suit: CardSuit}|null
     */
    public static function parse(string $code): ?array
    {
        $code = strtoupper(trim($code));

        if ($code === '' || str_starts_with($code, 'JOKER')) {
            return null;
        }

        $code = explode('#', $code)[0];

        $suitCode = substr($code, -1);
        $rankCode = substr($code, 0, -1);

        $suit = match ($suitCode) {
            'H' => CardSuit::Hearts,
            'D' => CardSuit::Diamonds,
            'C' => CardSuit::Clubs,
            'S' => CardSuit::Spades,
            default => null,
        };

        if ($suit === null) {
            return null;
        }

        foreach (CardRank::standard() as $rank) {
            if ($rank->code() === $rankCode) {
                return ['rank' => $rank, 'suit' => $suit];
            }
        }

        return null;
    }

    /**
     * Every code a host may pick as the "specific card" that decides who starts.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public static function selectableCards(): array
    {
        $cards = [];

        foreach (CardSuit::standard() as $suit) {
            foreach (CardRank::standard() as $rank) {
                $cards[] = [
                    'code' => self::make($rank, $suit),
                    'label' => "{$rank->label()} of {$suit->label()}",
                ];
            }
        }

        return $cards;
    }
}
