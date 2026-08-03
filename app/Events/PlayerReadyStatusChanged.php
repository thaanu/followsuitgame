<?php

namespace App\Events;

class PlayerReadyStatusChanged extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'player.ready';
    }
}
