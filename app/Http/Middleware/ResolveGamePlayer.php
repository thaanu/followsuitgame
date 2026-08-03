<?php

namespace App\Http\Middleware;

use App\Game\Services\ReconnectionService;
use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches the Follow Suit player this request belongs to, resolved from the
 * browser token in the header or the session, and refreshes their presence.
 */
class ResolveGamePlayer
{
    public const ATTRIBUTE = 'follow_suit.player';

    public function __construct(private readonly ReconnectionService $reconnection) {}

    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');

        if ($game instanceof Game) {
            $player = $this->reconnection->resolve($request, $game);

            if ($player !== null) {
                $request->attributes->set(self::ATTRIBUTE, $player);

                // Keep the session in step with a token that arrived by header, so a
                // later plain navigation still resolves the same seat.
                if ($this->reconnection->sessionToken($request, $game) === null) {
                    foreach ($this->reconnection->candidateTokens($request, $game) as $token) {
                        if ($this->reconnection->playerForToken($game, $token) !== null) {
                            $this->reconnection->remember($request, $game, $token);
                            break;
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
