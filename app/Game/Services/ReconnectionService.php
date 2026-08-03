<?php

namespace App\Game\Services;

use App\Enums\ConnectionStatus;
use App\Enums\DisconnectBehaviour;
use App\Enums\GameActionType;
use App\Game\Support\ActivityMessage;
use App\Game\Support\PlayerToken;
use App\Jobs\ApplyDisconnectionPolicy;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Player identity across page refreshes and dropped connections.
 *
 * Identity is a random token held by the browser. The server stores only its hash,
 * and mirrors the token into the session so a plain navigation still resolves the
 * player without JavaScript having to attach a header.
 */
class ReconnectionService
{
    public const SESSION_KEY = 'follow_suit.players';

    public const TOKEN_HEADER = 'X-Player-Token';

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly GameBroadcaster $broadcaster,
    ) {}

    /**
     * Resolve the player this request belongs to, if any.
     *
     * Checked in order: the explicit header Echo and the game API send, then the
     * server-side session.
     */
    public function resolve(Request $request, Game $game): ?GamePlayer
    {
        foreach ($this->candidateTokens($request, $game) as $token) {
            $player = $this->playerForToken($game, $token);

            if ($player !== null) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Every token this request could be carrying for the given game.
     *
     * @return array<int, string>
     */
    public function candidateTokens(Request $request, Game $game): array
    {
        $tokens = [
            (string) $request->header(self::TOKEN_HEADER, ''),
            (string) $request->input('player_token', ''),
            (string) $this->sessionToken($request, $game),
        ];

        return array_values(array_unique(array_filter($tokens, fn (string $token) => $token !== '')));
    }

    public function playerForToken(Game $game, string $token): ?GamePlayer
    {
        if (! PlayerToken::isWellFormed($token)) {
            return null;
        }

        return $game->players()
            ->where('secure_token_hash', PlayerToken::hash($token))
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Remember a player's token in the session for this browser.
     */
    public function remember(Request $request, Game $game, string $token): void
    {
        $players = (array) $request->session()->get(self::SESSION_KEY, []);
        $players[$game->public_id] = $token;

        $request->session()->put(self::SESSION_KEY, $players);
    }

    public function forget(Request $request, Game $game): void
    {
        $players = (array) $request->session()->get(self::SESSION_KEY, []);
        unset($players[$game->public_id]);

        $request->session()->put(self::SESSION_KEY, $players);
    }

    public function sessionToken(Request $request, Game $game): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $players = (array) $request->session()->get(self::SESSION_KEY, []);

        return $players[$game->public_id] ?? null;
    }

    /**
     * Mark a player as present. Called on every page load and by the heartbeat.
     */
    public function markConnected(Game $game, GamePlayer $player): void
    {
        $wasDisconnected = ! $player->isConnected();

        $player->forceFill([
            'connection_status' => ConnectionStatus::Connected,
            'disconnected_at' => null,
            'last_seen_at' => now(),
        ])->save();

        if (! $wasDisconnected) {
            return;
        }

        $this->activity->log(
            game: $game,
            type: GameActionType::PlayerReconnected,
            player: $player,
            message: ActivityMessage::reconnected($player),
        );

        // A paused game resumes as soon as everybody is back.
        if ($game->is_paused && $this->allConnected($game)) {
            $game->forceFill(['is_paused' => false])->save();
        }

        $game->forceFill(['state_version' => $game->state_version + 1])->save();

        $this->broadcaster->connectionChanged($game, $player, true);
    }

    /**
     * Mark a player as gone and apply the configured disconnect behaviour.
     */
    public function markDisconnected(Game $game, GamePlayer $player): void
    {
        if (! $player->isConnected()) {
            return;
        }

        $player->forceFill([
            'connection_status' => ConnectionStatus::Disconnected,
            'disconnected_at' => now(),
        ])->save();

        $this->activity->log(
            game: $game,
            type: GameActionType::PlayerDisconnected,
            player: $player,
            message: ActivityMessage::disconnected($player),
        );

        if ($game->ruleConfiguration()->disconnectBehaviour === DisconnectBehaviour::PauseGame
            && $game->status->isPlayable()) {
            $game->forceFill(['is_paused' => true])->save();
        }

        $game->forceFill(['state_version' => $game->state_version + 1])->save();

        $this->broadcaster->connectionChanged($game, $player, false);

        ApplyDisconnectionPolicy::dispatch((int) $game->id, (int) $player->id)
            ->delay(now()->addSeconds($game->ruleConfiguration()->reconnectionGraceSeconds));
    }

    /**
     * Players whose grace period has run out.
     *
     * @return Collection<int, GamePlayer>
     */
    public function playersPastGracePeriod(Game $game): Collection
    {
        $grace = $game->ruleConfiguration()->reconnectionGraceSeconds;

        return $game->activePlayers()
            ->where('connection_status', ConnectionStatus::Disconnected)
            ->where('disconnected_at', '<=', now()->subSeconds($grace))
            ->get();
    }

    public function allConnected(Game $game): bool
    {
        return $game->activePlayers()
            ->where('connection_status', ConnectionStatus::Disconnected)
            ->doesntExist();
    }

    /**
     * Everything a reconnecting player needs to resume, with no hidden information.
     *
     * @return array<string, mixed>
     */
    public function resumeState(Game $game, GamePlayer $player, GameStatePresenter $presenter): array
    {
        return [
            'public' => $presenter->publicState($game),
            'private' => $presenter->privateState($game, $player),
        ];
    }
}
