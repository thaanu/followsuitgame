<?php

namespace App\Enums;

enum UnableToFollowAction: string
{
    case CollectPile = 'collect_pile';
    case DrawCards = 'draw_cards';
    case SkipTurn = 'skip_turn';

    public function label(): string
    {
        return match ($this) {
            self::CollectPile => 'Collect the entire pile',
            self::DrawCards => 'Draw cards from the deck',
            self::SkipTurn => 'Skip the turn',
        };
    }
}
