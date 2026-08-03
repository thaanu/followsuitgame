<?php

namespace App\Http\Controllers;

use App\Enums\CardSuit;
use App\Game\Services\GameplayService;
use App\Http\Controllers\Concerns\InteractsWithGamePlayer;
use App\Http\Requests\PlayCardRequest;
use App\Http\Requests\ResolveTurnRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;

/**
 * The two moves a player can make. All the work happens in {@see GameplayService};
 * this controller only unwraps the request.
 */
class TurnController extends Controller
{
    use InteractsWithGamePlayer;

    public function __construct(private readonly GameplayService $gameplay) {}

    public function play(PlayCardRequest $request, Game $game): RedirectResponse
    {
        $player = $this->authorizePlayer($request, 'act', $game);

        $this->gameplay->playCard(
            game: $game,
            player: $player,
            cardId: (int) $request->validated('card_id'),
            chosenSuit: CardSuit::tryFrom((string) $request->validated('chosen_suit', '')),
            expectedStateVersion: (int) $request->validated('state_version'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        );

        return back();
    }

    /**
     * Resolve a turn where the player cannot follow suit, or is voluntarily taking
     * the pile. The configured rules decide what actually happens.
     */
    public function resolve(ResolveTurnRequest $request, Game $game): RedirectResponse
    {
        $player = $this->authorizePlayer($request, 'act', $game);

        $this->gameplay->resolveStuckTurn(
            game: $game,
            player: $player,
            expectedStateVersion: (int) $request->validated('state_version'),
            idempotencyKey: (string) $request->validated('idempotency_key'),
        );

        return back();
    }
}
