<?php

namespace App\Enums;

enum TieBreakRule: string
{
    case FewerCollectedCards = 'fewer_collected_cards';
    case FewerCollectedPiles = 'fewer_collected_piles';
    case Shared = 'shared';
    case TieBreakerRound = 'tie_breaker_round';

    public function label(): string
    {
        return match ($this) {
            self::FewerCollectedCards => 'Player with fewer collected cards wins',
            self::FewerCollectedPiles => 'Player with fewer collected piles wins',
            self::Shared => 'Shared result',
            self::TieBreakerRound => 'Play a tie-breaker round',
        };
    }
}
