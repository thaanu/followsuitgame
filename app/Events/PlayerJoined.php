<?php

namespace App\Events;

class PlayerJoined extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'player.joined';
    }
}
