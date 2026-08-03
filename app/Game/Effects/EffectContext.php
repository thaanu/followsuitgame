<?php

namespace App\Game\Effects;

use App\Enums\CardSuit;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;

/**
 * Mutable scratch space passed to every special card effect handler for a single
 * played card. Handlers record what they want to happen; the caller applies it.
 */
class EffectContext
{
    /** @var array<int, string> */
    public array $messages = [];

    /**
     * Extra seats to move past when the turn advances, raised by "skip" effects.
     */
    public int $extraSkips = 0;

    public bool $grantsExtraTurn = false;

    public bool $clearsPile = false;

    public function __construct(
        public readonly Game $game,
        public readonly GamePlayer $player,
        public readonly GameCard $card,
        public readonly ?CardSuit $chosenSuit = null,
    ) {}

    public function message(string $message): void
    {
        $this->messages[] = $message;
    }
}
