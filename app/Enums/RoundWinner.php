<?php

namespace App\Enums;

enum RoundWinner: string
{
    case HighestCard = 'highest_card';
    case LowestCard = 'lowest_card';

    public function label(): string
    {
        return match ($this) {
            self::HighestCard => 'Highest card wins the round',
            self::LowestCard => 'Lowest card wins the round',
        };
    }
}
