<?php

namespace App\Events;

class CardPlayed extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'card.played';
    }
}
