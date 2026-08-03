<?php

namespace App\Enums;

enum AceRule: string
{
    case AceHigh = 'ace_high';
    case AceLow = 'ace_low';
    case RankIrrelevant = 'rank_irrelevant';

    public function label(): string
    {
        return match ($this) {
            self::AceHigh => 'Ace is high',
            self::AceLow => 'Ace is low',
            self::RankIrrelevant => 'Card rank does not matter',
        };
    }
}
