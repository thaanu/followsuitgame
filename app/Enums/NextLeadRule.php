<?php

namespace App\Enums;

enum NextLeadRule: string
{
    case Collector = 'collector';
    case NextInOrder = 'next_in_order';
    case LastFollower = 'last_follower';

    public function label(): string
    {
        return match ($this) {
            self::Collector => 'The player who collected the pile',
            self::NextInOrder => 'The next player in turn order',
            self::LastFollower => 'The player who last followed suit',
        };
    }
}
