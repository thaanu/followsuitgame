<?php

namespace App\Enums;

enum DealMethod: string
{
    case AllEvenly = 'all_evenly';
    case FixedCount = 'fixed_count';

    public function label(): string
    {
        return match ($this) {
            self::AllEvenly => 'Deal all cards evenly',
            self::FixedCount => 'Deal a fixed number of cards',
        };
    }
}
