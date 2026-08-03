<?php

namespace App\Enums;

enum StartingPlayerMethod: string
{
    case Host = 'host';
    case Random = 'random';
    case SpecificCard = 'specific_card';

    public function label(): string
    {
        return match ($this) {
            self::Host => 'The host starts',
            self::Random => 'A random player starts',
            self::SpecificCard => 'The player holding a specific card starts',
        };
    }
}
