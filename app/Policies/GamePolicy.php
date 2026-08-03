<?php

namespace App\Policies;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Auth\Access\Response;

/**
 * Authorisation for the actions only certain seats may take.
 *
 * The "user" here is a {@see GamePlayer} — Follow Suit has no accounts, so a seat is
 * the identity. Every check also confirms the player belongs to the game in question,
 * which is what stops a token from one game acting on another.
 */
class GamePolicy
{
    /**
     * Whether this player is seated in this game at all.
     */
    public function view(GamePlayer $player, Game $game): Response
    {
        return $this->belongsTo($player, $game)
            ? Response::allow()
            : Response::deny('You are not part of this game.');
    }

    public function start(GamePlayer $player, Game $game): Response
    {
        return $this->asHostInLobby($player, $game);
    }

    public function updateSettings(GamePlayer $player, Game $game): Response
    {
        return $this->asHostInLobby($player, $game);
    }

    public function removePlayer(GamePlayer $player, Game $game): Response
    {
        return $this->asHostInLobby($player, $game);
    }

    public function cancel(GamePlayer $player, Game $game): Response
    {
        return $this->asHost($player, $game);
    }

    public function rematch(GamePlayer $player, Game $game): Response
    {
        if (! $this->asHost($player, $game)->allowed()) {
            return Response::deny('Only the host can do that.');
        }

        return $game->status->isFinished()
            ? Response::allow()
            : Response::deny('Finish this game before starting a rematch.');
    }

    /**
     * Whether this player may take a turn right now.
     */
    public function act(GamePlayer $player, Game $game): Response
    {
        if (! $this->belongsTo($player, $game)) {
            return Response::deny('You are not part of this game.');
        }

        if (! $game->status->isPlayable() || $game->is_paused) {
            return Response::deny('This game is not accepting moves right now.');
        }

        return $game->current_player_id === $player->id
            ? Response::allow()
            : Response::deny('It is not your turn.');
    }

    private function asHost(GamePlayer $player, Game $game): Response
    {
        return $this->belongsTo($player, $game) && $player->is_host
            ? Response::allow()
            : Response::deny('Only the host can do that.');
    }

    private function asHostInLobby(GamePlayer $player, Game $game): Response
    {
        if (! $this->asHost($player, $game)->allowed()) {
            return Response::deny('Only the host can do that.');
        }

        return $game->status === GameStatus::Lobby
            ? Response::allow()
            : Response::deny('This game has already started.');
    }

    private function belongsTo(GamePlayer $player, Game $game): bool
    {
        return $player->game_id === $game->id && ! $player->hasLeft();
    }
}
