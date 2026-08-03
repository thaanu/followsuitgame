<?php

namespace App\Events;

class GameSettingsUpdated extends PublicGameEvent
{
    public function broadcastAs(): string
    {
        return 'game.settings-updated';
    }
}
