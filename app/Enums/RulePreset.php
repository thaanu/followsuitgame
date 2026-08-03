<?php

namespace App\Enums;

enum RulePreset: string
{
    case Classic = 'classic';
    case Quick = 'quick';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic Follow Suit',
            self::Quick => 'Quick Game',
            self::Custom => 'Custom',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Deal every card out. Follow the suit, or collect the whole pile into your hand. First player to empty their hand wins.',
            self::Quick => 'Seven cards each. Collect the pile as penalty cards that never come back. Fewest penalty cards wins.',
            self::Custom => 'Configure every rule yourself.',
        };
    }
}
