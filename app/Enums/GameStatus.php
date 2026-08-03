<?php

namespace App\Enums;

enum GameStatus: string
{
    case Lobby = 'lobby';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Lobby => 'Waiting in lobby',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isAcceptingPlayers(): bool
    {
        return $this === self::Lobby;
    }

    public function isPlayable(): bool
    {
        return $this === self::InProgress;
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
