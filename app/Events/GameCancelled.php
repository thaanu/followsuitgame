<?php

namespace App\Events;

class GameCancelled extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'game.cancelled';
    }
}
