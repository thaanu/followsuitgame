<?php

namespace App\Events;

class PlayerDisconnected extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'player.disconnected';
    }
}
