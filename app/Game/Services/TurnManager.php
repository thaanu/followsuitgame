<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Enums\TurnDirection;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Collection;

/**
 * Owns the seat pointer: who plays next, in which direction, and by when.
 */
class TurnManager
{
    /**
     * Every seated player who still holds cards, in seat order.
     *
     * @return Collection<int, GamePlayer>
     */
    public function eligiblePlayers(Game $game): Collection
    {
        return $game->activePlayers()
            ->whereHas('cards', fn ($query) => $query->where('location', CardLocation::PlayerHand))
            ->orderBy('seat_number')
            ->get()
            ->values();
    }

    /**
     * The player who takes the turn after `$from`, walking `$steps` seats in the
     * game's current direction and skipping anybody who has run out of cards.
     */
    public function playerAfter(Game $game, ?GamePlayer $from, int $steps = 1): ?GamePlayer
    {
        $eligible = $this->eligiblePlayers($game);

        if ($eligible->isEmpty()) {
            return null;
        }

        if ($from === null) {
            return $eligible->first();
        }

        $index = $eligible->search(fn (GamePlayer $player) => $player->id === $from->id);

        if ($index === false) {
            // The player we are moving on from has already left the rotation, so pick
            // the next seat after theirs by seat number.
            return $this->nextBySeat($game, $eligible, $from);
        }

        $count = $eligible->count();
        $target = (($index + ($steps * $game->direction->step())) % $count + $count) % $count;

        return $eligible->get($target);
    }

    /**
     * Point the game at a player and, when the turn timer is enabled, set the
     * server-authoritative deadline for their turn.
     */
    public function assignTurn(Game $game, ?GamePlayer $player): void
    {
        $rules = $game->ruleConfiguration();

        $game->current_player_id = $player?->id;
        $game->turn_number = $game->turn_number + 1;
        $game->turn_deadline_at = $player !== null && $rules->timerEnabled
            ? now()->addSeconds($rules->timerSeconds)
            : null;
    }

    /**
     * @param  Collection<int, GamePlayer>  $eligible
     */
    private function nextBySeat(Game $game, Collection $eligible, GamePlayer $from): GamePlayer
    {
        $ordered = $game->direction === TurnDirection::Clockwise
            ? $eligible
            : $eligible->reverse()->values();

        $after = $game->direction === TurnDirection::Clockwise
            ? $ordered->first(fn (GamePlayer $player) => $player->seat_number > $from->seat_number)
            : $ordered->first(fn (GamePlayer $player) => $player->seat_number < $from->seat_number);

        return $after ?? $ordered->first();
    }
}
