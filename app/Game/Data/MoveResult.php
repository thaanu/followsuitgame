<?php

namespace App\Game\Data;

use App\Models\GamePlayer;

/**
 * What a committed move changed, so callers know which players need a private update.
 */
final class MoveResult
{
    /**
     * @param  array<int, GamePlayer>  $playersNeedingHandUpdate
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public bool $gameCompleted = false,
        public bool $pileCollected = false,
        public bool $playerFinished = false,
        public bool $roundCompleted = false,
        public array $playersNeedingHandUpdate = [],
        public array $messages = [],
    ) {}

    public function needsHandUpdate(GamePlayer $player): self
    {
        foreach ($this->playersNeedingHandUpdate as $existing) {
            if ($existing->id === $player->id) {
                return $this;
            }
        }

        $this->playersNeedingHandUpdate[] = $player;

        return $this;
    }

    public function message(string $message): self
    {
        $this->messages[] = $message;

        return $this;
    }
}
