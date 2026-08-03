<?php

namespace App\Enums;

enum PunishmentSeverity: string
{
    case Fun = 'fun';
    case Mild = 'mild';
    case Challenging = 'challenging';

    public function label(): string
    {
        return match ($this) {
            self::Fun => 'Fun',
            self::Mild => 'Mild',
            self::Challenging => 'Challenging',
        };
    }
}
