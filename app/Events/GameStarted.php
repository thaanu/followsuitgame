<?php

namespace App\Events;

class GameStarted extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'game.started';
    }
}
