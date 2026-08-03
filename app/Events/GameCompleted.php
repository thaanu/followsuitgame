<?php

namespace App\Events;

class GameCompleted extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'game.completed';
    }
}
