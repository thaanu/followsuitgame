<?php

namespace App\Enums;

enum PileEndCondition: string
{
    case CannotFollowSuit = 'cannot_follow_suit';
    case EveryPlayerPlayed = 'every_player_played';
    case FixedCardCount = 'fixed_card_count';
    case SpecialCard = 'special_card';

    public function label(): string
    {
        return match ($this) {
            self::CannotFollowSuit => 'When a player cannot follow suit',
            self::EveryPlayerPlayed => 'After every player has played one card',
            self::FixedCardCount => 'After a set number of cards',
            self::SpecialCard => 'When a special card is played',
        };
    }
}
