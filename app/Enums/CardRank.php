<?php

namespace App\Enums;

enum CardRank: string
{
    case Ace = 'ace';
    case Two = 'two';
    case Three = 'three';
    case Four = 'four';
    case Five = 'five';
    case Six = 'six';
    case Seven = 'seven';
    case Eight = 'eight';
    case Nine = 'nine';
    case Ten = 'ten';
    case Jack = 'jack';
    case Queen = 'queen';
    case King = 'king';
    case Joker = 'joker';

    /**
     * The thirteen ranks present in every standard suit, in ace-low order.
     *
     * @return array<int, self>
     */
    public static function standard(): array
    {
        return [
            self::Ace, self::Two, self::Three, self::Four, self::Five,
            self::Six, self::Seven, self::Eight, self::Nine, self::Ten,
            self::Jack, self::Queen, self::King,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Ace => 'Ace',
            self::Two => '2',
            self::Three => '3',
            self::Four => '4',
            self::Five => '5',
            self::Six => '6',
            self::Seven => '7',
            self::Eight => '8',
            self::Nine => '9',
            self::Ten => '10',
            self::Jack => 'Jack',
            self::Queen => 'Queen',
            self::King => 'King',
            self::Joker => 'Joker',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Ace => 'A',
            self::Two => '2',
            self::Three => '3',
            self::Four => '4',
            self::Five => '5',
            self::Six => '6',
            self::Seven => '7',
            self::Eight => '8',
            self::Nine => '9',
            self::Ten => '10',
            self::Jack => 'J',
            self::Queen => 'Q',
            self::King => 'K',
            self::Joker => 'JK',
        };
    }

    /**
     * The comparable value of this rank under the given ace rule.
     */
    public function value(AceRule $aceRule): int
    {
        if ($this === self::Joker) {
            return 0;
        }

        if ($this === self::Ace) {
            return $aceRule === AceRule::AceHigh ? 14 : 1;
        }

        return match ($this) {
            self::Two => 2,
            self::Three => 3,
            self::Four => 4,
            self::Five => 5,
            self::Six => 6,
            self::Seven => 7,
            self::Eight => 8,
            self::Nine => 9,
            self::Ten => 10,
            self::Jack => 11,
            self::Queen => 12,
            self::King => 13,
        };
    }
}
