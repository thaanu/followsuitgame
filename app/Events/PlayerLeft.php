<?php

namespace App\Events;

class PlayerLeft extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'player.left';
    }
}
