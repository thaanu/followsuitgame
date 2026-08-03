<?php

namespace App\Http\Controllers\Concerns;

use App\Game\Exceptions\GameException;
use App\Http\Middleware\ResolveGamePlayer;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Policies\GamePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Access to the player the current request belongs to, and the authorisation checks
 * that {@see GamePolicy} defines for them.
 */
trait InteractsWithGamePlayer
{
    protected function currentPlayer(Request $request): ?GamePlayer
    {
        $player = $request->attributes->get(ResolveGamePlayer::ATTRIBUTE);

        return $player instanceof GamePlayer ? $player : null;
    }

    protected function requirePlayer(Request $request): GamePlayer
    {
        return $this->currentPlayer($request) ?? throw GameException::notInGame();
    }

    /**
     * Run a policy ability for the acting player, translating a denial into the
     * GameException the frontend knows how to render.
     */
    protected function authorizePlayer(Request $request, string $ability, Game $game): GamePlayer
    {
        $player = $this->requirePlayer($request);

        $response = Gate::forUser($player)->inspect($ability, $game);

        if ($response->denied()) {
            throw $this->exceptionFor($ability, (string) $response->message());
        }

        return $player;
    }

    /**
     * Map a policy denial onto the reason code that best describes it, so the browser
     * can show the right screen.
     */
    private function exceptionFor(string $ability, string $message): GameException
    {
        return match (true) {
            str_contains($message, 'not part of this game') => GameException::notInGame(),
            str_contains($message, 'Only the host') => GameException::notHost(),
            str_contains($message, 'already started') => GameException::alreadyStarted(),
            str_contains($message, 'not accepting moves') => GameException::gameNotPlayable(),
            str_contains($message, 'not your turn') => GameException::notYourTurn(),
            default => GameException::illegalMove($message),
        };
    }
}
