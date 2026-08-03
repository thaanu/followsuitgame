<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Enums\GameStatus;
use App\Game\Exceptions\GameException;
use App\Models\Game;
use App\Models\GameAction;
use App\Models\GameCard;
use App\Models\GamePlayer;

/**
 * Server-side gatekeeping for every gameplay action.
 *
 * The frontend's opinion about legality is never trusted; each check below is
 * re-run inside the locked transaction that commits the move.
 */
class MoveValidationService
{
    public function __construct(private readonly RuleEngine $rules) {}

    /**
     * Assert the game will accept an action from this player right now.
     */
    public function assertCanAct(Game $game, GamePlayer $player, ?int $expectedStateVersion, ?string $idempotencyKey = null): void
    {
        if ($game->status !== GameStatus::InProgress) {
            throw GameException::gameNotPlayable();
        }

        if ($game->is_paused) {
            throw GameException::gameNotPlayable();
        }

        if ($player->game_id !== $game->id || $player->hasLeft()) {
            throw GameException::notInGame();
        }

        if ($game->current_player_id !== $player->id) {
            throw GameException::notYourTurn();
        }

        if ($expectedStateVersion !== null && $expectedStateVersion !== (int) $game->state_version) {
            throw GameException::staleState();
        }

        if ($idempotencyKey !== null && $this->alreadyProcessed($game, $idempotencyKey)) {
            throw GameException::duplicateAction();
        }
    }

    public function alreadyProcessed(Game $game, string $idempotencyKey): bool
    {
        return GameAction::where('game_id', $game->id)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    /**
     * Resolve a card id into a card the player actually holds.
     */
    public function resolveOwnedCard(Game $game, GamePlayer $player, int $cardId): GameCard
    {
        $card = $game->cards()
            ->whereKey($cardId)
            ->where('player_id', $player->id)
            ->where('location', CardLocation::PlayerHand)
            ->first();

        if ($card === null) {
            throw GameException::cardNotOwned();
        }

        return $card;
    }

    /**
     * Assert the card may be played into the current pile under the active rules.
     */
    public function assertCardIsLegal(Game $game, GamePlayer $player, GameCard $card): void
    {
        $legal = $this->rules->legalCards($game, $player);

        if (! $legal->contains(fn (GameCard $legalCard) => $legalCard->id === $card->id)) {
            $suit = $game->active_suit?->label() ?? 'the active suit';

            throw GameException::illegalMove("You must follow {$suit}.");
        }
    }

    /**
     * Assert the player is genuinely stuck before letting them take the pile.
     */
    public function assertMayCollect(Game $game, GamePlayer $player): void
    {
        if ($this->rules->canFollowSuit($game, $player) && ! $this->rules->mayVoluntarilyCollect($game, $player)) {
            throw GameException::illegalMove('You can follow suit, so you must play a card.');
        }
    }
}
