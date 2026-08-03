<?php

namespace App\Enums;

enum DeckType: string
{
    case Standard52 = 'standard_52';
    case Standard54WithJokers = 'standard_54_jokers';

    public function label(): string
    {
        return match ($this) {
            self::Standard52 => 'Standard 52-card deck (no Jokers)',
            self::Standard54WithJokers => 'Standard deck with 2 Jokers',
        };
    }

    public function jokerCount(): int
    {
        return $this === self::Standard54WithJokers ? 2 : 0;
    }
}
