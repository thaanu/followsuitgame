<?php

namespace App\Events;

class PlayerReconnected extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'player.reconnected';
    }
}
