<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\GameCreationService;
use App\Game\Services\GameStatePresenter;
use App\Game\Services\ReconnectionService;
use App\Game\Services\WinningConditionService;
use App\Http\Controllers\Concerns\InteractsWithGamePlayer;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultsController extends Controller
{
    use InteractsWithGamePlayer;

    public function __construct(
        private readonly WinningConditionService $winning,
        private readonly GameStatePresenter $presenter,
        private readonly GameCreationService $creation,
        private readonly ReconnectionService $reconnection,
        private readonly GameBroadcaster $broadcaster,
    ) {}

    public function show(Request $request, Game $game): Response|RedirectResponse
    {
        if ($game->status === GameStatus::Lobby) {
            return to_route('games.lobby', $game);
        }

        if ($game->status->isPlayable()) {
            return to_route('games.table', $game);
        }

        $player = $this->currentPlayer($request);
        $rematch = $game->rematch_game_id === null ? null : Game::find($game->rematch_game_id);

        return Inertia::render('Games/Results', [
            'state' => $this->presenter->publicState($game),
            'results' => $this->winning->results($game),
            'cancelled' => $game->status === GameStatus::Cancelled,
            'me' => $player === null ? null : [
                'id' => $player->public_id,
                'nickname' => $player->nickname,
                'is_host' => (bool) $player->is_host,
            ],
            'rulesSummary' => $game->ruleConfiguration()->summary(),
            'rematch' => $rematch === null ? null : [
                'code' => $rematch->game_code,
                'url' => $rematch->invitationUrl(),
            ],
        ]);
    }

    /**
     * The host starting a fresh game with the same rules. Everybody else follows the
     * link that appears on their results screen.
     */
    public function rematch(Request $request, Game $game): RedirectResponse
    {
        $host = $this->authorizePlayer($request, 'rematch', $game);

        if ($game->rematch_game_id !== null) {
            $existing = Game::find($game->rematch_game_id);

            if ($existing !== null && ! $existing->status->isFinished()) {
                return to_route('games.lobby', $existing);
            }
        }

        ['game' => $rematch, 'token' => $token] = $this->creation->create(
            hostNickname: $host->nickname,
            maximumPlayers: (int) $game->maximum_players,
            name: $game->name,
            preset: $game->rule_preset,
            rules: $game->ruleConfiguration()->toArray(),
            punishment: $game->punishmentConfiguration()?->toArray(),
        );

        $game->forceFill([
            'rematch_game_id' => $rematch->id,
            'state_version' => $game->state_version + 1,
        ])->save();

        $this->reconnection->remember($request, $rematch, $token);
        $this->broadcaster->settingsUpdated($game);

        return to_route('games.lobby', $rematch)->with('playerToken', $token);
    }
}
