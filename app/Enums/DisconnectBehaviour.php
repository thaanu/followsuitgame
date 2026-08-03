<?php

namespace App\Enums;

enum DisconnectBehaviour: string
{
    case PauseGame = 'pause_game';
    case SkipAfterTimeout = 'skip_after_timeout';
    case AutoPlay = 'auto_play';
    case HostDecides = 'host_decides';

    public function label(): string
    {
        return match ($this) {
            self::PauseGame => 'Pause the game',
            self::SkipAfterTimeout => 'Skip the player after the grace period',
            self::AutoPlay => 'Automatically play a valid card',
            self::HostDecides => 'The host decides',
        };
    }
}
