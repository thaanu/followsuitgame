<?php

namespace App\Enums;

enum TimeoutAction: string
{
    case PlayRandomLegal = 'play_random_legal';
    case CollectPile = 'collect_pile';
    case SkipTurn = 'skip_turn';
    case HostDecides = 'host_decides';

    public function label(): string
    {
        return match ($this) {
            self::PlayRandomLegal => 'Play a random legal card',
            self::CollectPile => 'Collect the pile',
            self::SkipTurn => 'Skip the turn',
            self::HostDecides => 'The host decides',
        };
    }
}
