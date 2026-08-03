<?php

namespace App\Events;

class PileCollected extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'pile.collected';
    }
}
