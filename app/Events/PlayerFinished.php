<?php

namespace App\Events;

class PlayerFinished extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'player.finished';
    }
}
