<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Game\Exceptions\GameException;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\GameJoiningService;
use App\Game\Services\ReconnectionService;
use App\Http\Requests\JoinGameRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    public function __construct(
        private readonly GameJoiningService $joining,
        private readonly ReconnectionService $reconnection,
        private readonly GameBroadcaster $broadcaster,
    ) {}

    /**
     * The "enter a code" screen for players who were sent a code rather than a link.
     */
    public function index(): Response
    {
        return Inertia::render('Games/JoinByCode');
    }

    /**
     * Open an invitation link.
     */
    public function show(Request $request, string $code): Response|RedirectResponse
    {
        try {
            $game = $this->joining->resolveInvitation($code, $request->query('token'));
        } catch (GameException $exception) {
            return $this->invalid($exception);
        }

        // A browser that already holds a seat in this game goes straight back to it.
        $existing = $this->reconnection->resolve($request, $game);

        if ($existing !== null) {
            return $this->redirectToGame($game);
        }

        try {
            $this->joining->assertJoinable($game);
        } catch (GameException $exception) {
            return $this->invalid($exception, $game);
        }

        return Inertia::render('Games/Join', [
            'game' => [
                'code' => $game->game_code,
                'name' => $game->name,
                'host' => $game->host?->nickname,
                'player_count' => $game->activePlayers()->count(),
                'maximum_players' => (int) $game->maximum_players,
                'rules_summary' => $game->ruleConfiguration()->summary(),
                'rule_preset_label' => $game->rule_preset->label(),
                'punishment' => $game->punishmentConfiguration()?->summary(),
            ],
            'token' => $request->query('token'),
            'takenNicknames' => $game->activePlayers()->pluck('nickname')->all(),
            'punishmentNotice' => config('followsuit.punishment_notice'),
        ]);
    }

    /**
     * Take a seat.
     */
    public function store(JoinGameRequest $request, string $code): RedirectResponse
    {
        $game = $this->joining->resolveInvitation($code, $request->validated('token'));

        $existingToken = $request->validated('player_token')
            ?? $this->reconnection->sessionToken($request, $game);

        ['player' => $player, 'token' => $token, 'rejoined' => $rejoined] = $this->joining->join(
            game: $game,
            nickname: (string) $request->validated('nickname'),
            existingToken: $existingToken,
        );

        $this->reconnection->remember($request, $game, $token);

        if (! $rejoined) {
            $this->broadcaster->playerJoined($game);
        }

        return $this->redirectToGame($game)->with('playerToken', $token);
    }

    private function redirectToGame(Game $game): RedirectResponse
    {
        return match (true) {
            $game->status->isPlayable() => to_route('games.table', $game),
            $game->status === GameStatus::Completed => to_route('games.results', $game),
            default => to_route('games.lobby', $game),
        };
    }

    private function invalid(GameException $exception, ?Game $game = null): Response
    {
        return Inertia::render('Games/Invalid', [
            'reason' => $exception->reason,
            'message' => $exception->getMessage(),
            'gameCode' => $game?->game_code,
        ]);
    }
}
