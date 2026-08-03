<?php

namespace App\Game\Data;

use App\Models\GamePlayer;

/**
 * One row of the final results table.
 */
final readonly class Standing
{
    public function __construct(
        public GamePlayer $player,
        public int $position,
        public int $cardsRemaining,
        public int $cardsCollected,
        public int $pilesCollected,
        public int $failedFollowSuit,
        public bool $tied,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'player_id' => $this->player->public_id,
            'nickname' => $this->player->nickname,
            'initials' => $this->player->initials(),
            'is_host' => (bool) $this->player->is_host,
            'position' => $this->position,
            'cards_remaining' => $this->cardsRemaining,
            'cards_collected' => $this->cardsCollected,
            'piles_collected' => $this->pilesCollected,
            'failed_follow_suit' => $this->failedFollowSuit,
            'tied' => $this->tied,
        ];
    }
}
