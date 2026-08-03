<?php

namespace App\Enums;

enum CollectedCardsDestination: string
{
    case Hand = 'hand';
    case CollectedArea = 'collected_area';
    case CountOnly = 'count_only';

    public function label(): string
    {
        return match ($this) {
            self::Hand => 'Add them to the player\'s hand',
            self::CollectedArea => 'Move them to the player\'s penalty pile',
            self::CountOnly => 'Count them only, cards leave play',
        };
    }
}
