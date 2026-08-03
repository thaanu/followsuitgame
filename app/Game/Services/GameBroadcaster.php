<?php

namespace App\Game\Services;

use App\Events\CardPlayed;
use App\Events\GameCancelled;
use App\Events\GameCompleted;
use App\Events\GameSettingsUpdated;
use App\Events\GameStarted;
use App\Events\PileCollected;
use App\Events\PlayerDisconnected;
use App\Events\PlayerFinished;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\PlayerReadyStatusChanged;
use App\Events\PlayerReconnected;
use App\Events\PrivateStateUpdated;
use App\Events\PublicGameEvent;
use App\Events\TurnChanged;
use App\Game\Data\MoveResult;
use App\Jobs\EnforceTurnTimeout;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Contracts\Container\Container;

/**
 * Turns state changes into broadcasts.
 *
 * Public state goes to the whole table; a player's hand only ever goes to that
 * player's own private channel.
 */
class GameBroadcaster
{
    public function __construct(private readonly Container $container) {}

    /**
     * Broadcast the outcome of a move: the new public state for everyone, plus a
     * fresh hand for each player whose cards changed.
     */
    public function broadcastMove(Game $game, MoveResult $result): void
    {
        $game->refresh();

        if ($result->gameCompleted) {
            $this->publicEvent($game, GameCompleted::class);
        } elseif ($result->pileCollected) {
            $this->publicEvent($game, PileCollected::class);
        } else {
            $this->publicEvent($game, CardPlayed::class);
        }

        if ($result->playerFinished) {
            $this->publicEvent($game, PlayerFinished::class);
        }

        $this->publicEvent($game, TurnChanged::class);

        // Everybody's private view depends on whose turn it is, so refresh them all.
        foreach ($game->activePlayers()->get() as $player) {
            $this->privateState($game, $player);
        }

        $this->scheduleTurnTimer($game);
    }

    /**
     * Arm the server-side turn timer for whoever is now on play.
     *
     * The job carries the state version it was scheduled against, so it does nothing
     * if the player moves before it fires.
     */
    public function scheduleTurnTimer(Game $game): void
    {
        if (! $game->status->isPlayable() || $game->is_paused) {
            return;
        }

        if ($game->turn_deadline_at === null || $game->current_player_id === null) {
            return;
        }

        EnforceTurnTimeout::dispatch((int) $game->id, (int) $game->state_version)
            ->delay($game->turn_deadline_at->addSecond());
    }

    /**
     * @param  class-string<PublicGameEvent>  $event
     */
    public function lobbyUpdated(Game $game, string $event = PlayerJoined::class): void
    {
        $this->publicEvent($game->refresh(), $event, lobby: true);
    }

    public function playerJoined(Game $game): void
    {
        $this->lobbyUpdated($game, PlayerJoined::class);
    }

    public function playerLeft(Game $game): void
    {
        $this->lobbyUpdated($game, PlayerLeft::class);
    }

    public function readyChanged(Game $game): void
    {
        $this->lobbyUpdated($game, PlayerReadyStatusChanged::class);
    }

    public function settingsUpdated(Game $game): void
    {
        $this->lobbyUpdated($game, GameSettingsUpdated::class);
    }

    public function gameCancelled(Game $game): void
    {
        $this->lobbyUpdated($game, GameCancelled::class);
    }

    public function gameStarted(Game $game): void
    {
        $game->refresh();

        $this->publicEvent($game, GameStarted::class);

        foreach ($game->activePlayers()->get() as $player) {
            $this->privateState($game, $player);
        }

        $this->scheduleTurnTimer($game);
    }

    public function connectionChanged(Game $game, GamePlayer $player, bool $connected): void
    {
        $this->publicEvent(
            $game->refresh(),
            $connected ? PlayerReconnected::class : PlayerDisconnected::class,
            payload: ['player_id' => $player->public_id],
        );
    }

    public function privateState(Game $game, GamePlayer $player): void
    {
        PrivateStateUpdated::dispatch(
            $game->public_id,
            $player->public_id,
            $this->presenter()->privateState($game, $player),
        );
    }

    /**
     * @param  class-string<PublicGameEvent>  $event
     * @param  array<string, mixed>  $payload
     */
    private function publicEvent(Game $game, string $event, array $payload = [], bool $lobby = false): void
    {
        $state = $lobby
            ? $this->presenter()->lobbyState($game)
            : $this->presenter()->publicState($game);

        $event::dispatch($game->public_id, $state, $payload);
    }

    private function presenter(): GameStatePresenter
    {
        return $this->container->make(GameStatePresenter::class);
    }
}
