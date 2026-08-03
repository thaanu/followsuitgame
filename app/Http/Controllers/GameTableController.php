<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Game\Services\GameStatePresenter;
use App\Game\Services\ReconnectionService;
use App\Http\Controllers\Concerns\InteractsWithGamePlayer;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameTableController extends Controller
{
    use InteractsWithGamePlayer;

    public function __construct(
        private readonly GameStatePresenter $presenter,
        private readonly ReconnectionService $reconnection,
    ) {}

    public function show(Request $request, Game $game): Response|RedirectResponse
    {
        $player = $this->currentPlayer($request);

        if ($player === null) {
            return to_route('games.join.show', ['code' => $game->game_code]);
        }

        if ($game->status === GameStatus::Lobby) {
            return to_route('games.lobby', $game);
        }

        if ($game->status->isFinished()) {
            return to_route('games.results', $game);
        }

        $this->reconnection->markConnected($game, $player);

        return Inertia::render('Games/Table', [
            'state' => $this->presenter->publicState($game),
            'hand' => $this->presenter->privateState($game, $player),
            'me' => [
                'id' => $player->public_id,
                'nickname' => $player->nickname,
                'is_host' => (bool) $player->is_host,
                'seat_number' => (int) $player->seat_number,
            ],
            'playerToken' => $this->reconnection->sessionToken($request, $game),
            'rulesSummary' => $game->ruleConfiguration()->summary(),
            'punishment' => $game->punishmentConfiguration()?->summary(),
            'heartbeatSeconds' => (int) config('followsuit.heartbeat_seconds'),
        ]);
    }

    /**
     * A polling fallback and reconnection endpoint: the full state a player is
     * entitled to see, in one request.
     */
    public function state(Request $request, Game $game): JsonResponse
    {
        $player = $this->requirePlayer($request);

        $this->reconnection->markConnected($game, $player);

        return response()->json([
            'state' => $this->presenter->publicState($game),
            'hand' => $this->presenter->privateState($game, $player),
        ]);
    }

    /**
     * A lightweight presence ping. Returns the state version so a client that has
     * fallen behind knows to refetch.
     */
    public function heartbeat(Request $request, Game $game): JsonResponse
    {
        $player = $this->requirePlayer($request);

        $this->reconnection->markConnected($game, $player);

        return response()->json([
            'state_version' => (int) $game->state_version,
            'status' => $game->status->value,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * The screen shown while a player's browser is trying to get back into a game.
     */
    public function reconnect(Request $request, Game $game): Response
    {
        $player = $this->currentPlayer($request);

        return Inertia::render('Games/Reconnect', [
            'game' => [
                'id' => $game->public_id,
                'code' => $game->game_code,
                'name' => $game->name,
                'status' => $game->status->value,
                'status_label' => $game->status->label(),
            ],
            'found' => $player !== null,
            'nickname' => $player?->nickname,
        ]);
    }
}
