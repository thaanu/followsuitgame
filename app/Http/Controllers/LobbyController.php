<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\RulePreset;
use App\Game\Exceptions\GameException;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\GameCreationService;
use App\Game\Services\GameJoiningService;
use App\Game\Services\GameStartService;
use App\Game\Services\GameStatePresenter;
use App\Game\Services\ReconnectionService;
use App\Http\Controllers\Concerns\InteractsWithGamePlayer;
use App\Http\Requests\UpdateGameSettingsRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LobbyController extends Controller
{
    use InteractsWithGamePlayer;

    public function __construct(
        private readonly GameJoiningService $joining,
        private readonly GameStartService $starter,
        private readonly GameStatePresenter $presenter,
        private readonly GameBroadcaster $broadcaster,
        private readonly ReconnectionService $reconnection,
        private readonly GameCreationService $creation,
    ) {}

    public function show(Request $request, Game $game): Response|RedirectResponse
    {
        $player = $this->currentPlayer($request);

        if ($player === null) {
            return to_route('games.join.show', ['code' => $game->game_code]);
        }

        if ($game->status->isPlayable()) {
            return to_route('games.table', $game);
        }

        if ($game->status === GameStatus::Completed) {
            return to_route('games.results', $game);
        }

        $this->reconnection->markConnected($game, $player);

        return Inertia::render('Games/Lobby', [
            'state' => $this->presenter->lobbyState($game, includeInvitation: true),
            'me' => $this->me($player),
            'playerToken' => $this->reconnection->sessionToken($request, $game),
            'options' => GameController::ruleOptions(),
            'presets' => GameController::presets(),
            'punishmentNotice' => config('followsuit.punishment_notice'),
            'heartbeatSeconds' => (int) config('followsuit.heartbeat_seconds'),
        ]);
    }

    public function ready(Request $request, Game $game): RedirectResponse
    {
        $player = $this->authorizePlayer($request, 'view', $game);
        $this->assertInLobby($game);

        $this->joining->setReady($game, $player, $request->boolean('ready'));
        $this->broadcaster->readyChanged($game);

        return back();
    }

    public function acknowledgePunishment(Request $request, Game $game): RedirectResponse
    {
        $player = $this->authorizePlayer($request, 'view', $game);
        $this->assertInLobby($game);

        $this->joining->acknowledgePunishment($game, $player);
        $this->broadcaster->readyChanged($game);

        return back();
    }

    public function leave(Request $request, Game $game): RedirectResponse
    {
        $player = $this->authorizePlayer($request, 'view', $game);

        $this->joining->leave($game, $player);
        $this->reconnection->forget($request, $game);
        $this->broadcaster->playerLeft($game);

        return to_route('home');
    }

    /**
     * The host removing somebody from the lobby before the game starts.
     */
    public function removePlayer(Request $request, Game $game, GamePlayer $player): RedirectResponse
    {
        $host = $this->authorizePlayer($request, 'removePlayer', $game);

        if ($player->game_id !== $game->id) {
            throw GameException::notInGame();
        }

        if ($player->id === $host->id) {
            throw GameException::illegalMove('The host cannot remove themselves. Cancel the game instead.');
        }

        $this->joining->leave($game, $player, removedByHost: true);
        $this->broadcaster->playerLeft($game);

        return back();
    }

    public function updateSettings(UpdateGameSettingsRequest $request, Game $game): RedirectResponse
    {
        $this->authorizePlayer($request, 'updateSettings', $game);

        if ($game->activePlayers()->count() > (int) $request->validated('maximum_players')) {
            throw GameException::illegalMove('There are already more players in the lobby than that limit allows.');
        }

        $this->creation->updateSettings(
            game: $game,
            name: $request->validated('name'),
            maximumPlayers: (int) $request->validated('maximum_players'),
            preset: RulePreset::from((string) $request->validated('rule_preset')),
            rules: $request->ruleConfigurationArray(),
            punishment: $request->punishmentArray(),
        );

        $this->broadcaster->settingsUpdated($game);

        return back();
    }

    public function start(Request $request, Game $game): RedirectResponse
    {
        $this->authorizePlayer($request, 'start', $game);

        $this->starter->start($game);
        $this->broadcaster->gameStarted($game);

        return to_route('games.table', $game);
    }

    private function assertInLobby(Game $game): void
    {
        if ($game->status !== GameStatus::Lobby) {
            throw GameException::alreadyStarted();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function me(GamePlayer $player): array
    {
        return [
            'id' => $player->public_id,
            'nickname' => $player->nickname,
            'initials' => $player->initials(),
            'is_host' => (bool) $player->is_host,
            'is_ready' => (bool) $player->is_ready,
            'has_acknowledged_punishment' => $player->hasAcknowledgedPunishment(),
            'seat_number' => (int) $player->seat_number,
        ];
    }
}
