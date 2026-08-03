<?php

namespace App\Enums;

enum TurnDirection: string
{
    case Clockwise = 'clockwise';
    case Counterclockwise = 'counterclockwise';

    public function reversed(): self
    {
        return $this === self::Clockwise ? self::Counterclockwise : self::Clockwise;
    }

    public function step(): int
    {
        return $this === self::Clockwise ? 1 : -1;
    }

    public function label(): string
    {
        return $this === self::Clockwise ? 'Clockwise' : 'Counterclockwise';
    }
}
