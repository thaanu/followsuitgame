<?php

namespace App\Events;

class TurnChanged extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'turn.changed';
    }
}
