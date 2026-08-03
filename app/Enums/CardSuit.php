<?php

namespace App\Enums;

enum CardSuit: string
{
    case Hearts = 'hearts';
    case Diamonds = 'diamonds';
    case Clubs = 'clubs';
    case Spades = 'spades';
    case None = 'none';

    /**
     * The four suits present in every standard deck.
     *
     * @return array<int, self>
     */
    public static function standard(): array
    {
        return [self::Hearts, self::Diamonds, self::Clubs, self::Spades];
    }

    public function label(): string
    {
        return match ($this) {
            self::Hearts => 'Hearts',
            self::Diamonds => 'Diamonds',
            self::Clubs => 'Clubs',
            self::Spades => 'Spades',
            self::None => 'No suit',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Hearts => '♥',
            self::Diamonds => '♦',
            self::Clubs => '♣',
            self::Spades => '♠',
            self::None => '★',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Hearts => 'H',
            self::Diamonds => 'D',
            self::Clubs => 'C',
            self::Spades => 'S',
            self::None => 'X',
        };
    }

    public function isRed(): bool
    {
        return $this === self::Hearts || $this === self::Diamonds;
    }
}
