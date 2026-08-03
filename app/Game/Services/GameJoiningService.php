<?php

namespace App\Game\Services;

use App\Enums\ConnectionStatus;
use App\Enums\GameActionType;
use App\Enums\GameStatus;
use App\Game\Exceptions\GameException;
use App\Game\Support\ActivityMessage;
use App\Game\Support\GameCode;
use App\Game\Support\PlayerToken;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything between opening an invitation link and being seated in the lobby.
 */
class GameJoiningService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Resolve a game from an invitation, rejecting anything a player cannot join.
     */
    public function resolveInvitation(string $code, ?string $token = null): Game
    {
        $game = Game::where('game_code', GameCode::normalise($code))->first();

        if ($game === null) {
            throw GameException::invalidInvitation();
        }

        if ($token !== null && ! hash_equals($game->invitation_token, $token)) {
            throw GameException::invalidInvitation();
        }

        return $game;
    }

    /**
     * Assert the game is in a state that accepts new players.
     */
    public function assertJoinable(Game $game): void
    {
        match ($game->status) {
            GameStatus::Cancelled => throw GameException::gameCancelled(),
            GameStatus::Completed => throw GameException::gameCompleted(),
            GameStatus::InProgress => throw GameException::alreadyStarted(),
            GameStatus::Lobby => null,
        };

        if ($game->isExpired()) {
            throw GameException::expiredInvitation();
        }

        if ($game->activePlayers()->count() >= $game->maximum_players) {
            throw GameException::gameFull();
        }
    }

    /**
     * Find the seat that belongs to a browser token, if it still holds one.
     */
    public function playerForToken(Game $game, ?string $token): ?GamePlayer
    {
        if ($token === null || $token === '') {
            return null;
        }

        return $game->players()
            ->where('secure_token_hash', PlayerToken::hash($token))
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Seat a new player, or hand back the seat this browser already owns.
     *
     * @return array{player: GamePlayer, token: string, rejoined: bool}
     */
    public function join(Game $game, string $nickname, ?string $existingToken = null): array
    {
        // A player returning with a valid token keeps their seat instead of taking a
        // second one, even if the game has already started.
        if ($existing = $this->playerForToken($game, $existingToken)) {
            $existing->forceFill([
                'connection_status' => ConnectionStatus::Connected,
                'disconnected_at' => null,
                'last_seen_at' => now(),
            ])->save();

            return ['player' => $existing, 'token' => (string) $existingToken, 'rejoined' => true];
        }

        $this->assertJoinable($game);

        $token = PlayerToken::generate();

        return DB::transaction(function () use ($game, $nickname, $token) {
            /** @var Game $locked */
            $locked = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            $this->assertJoinable($locked);

            $taken = $locked->players()
                ->whereNull('left_at')
                ->whereRaw('LOWER(nickname) = ?', [mb_strtolower($nickname)])
                ->exists();

            if ($taken) {
                throw GameException::nicknameTaken();
            }

            $seat = (int) $locked->players()->max('seat_number') + 1;

            $player = GamePlayer::create([
                'public_id' => (string) Str::ulid(),
                'game_id' => $locked->id,
                'nickname' => $nickname,
                'secure_token_hash' => PlayerToken::hash($token),
                'seat_number' => $seat,
                'is_host' => false,
                'is_ready' => false,
                'joined_at' => now(),
                'last_seen_at' => now(),
            ]);

            $locked->forceFill(['state_version' => $locked->state_version + 1])->save();

            $this->activity->log(
                game: $locked,
                type: GameActionType::PlayerJoined,
                player: $player,
                message: ActivityMessage::playerJoined($player),
            );

            return ['player' => $player, 'token' => $token, 'rejoined' => false];
        });
    }

    /**
     * A player leaving the lobby of their own accord, or being removed by the host.
     */
    public function leave(Game $game, GamePlayer $player, bool $removedByHost = false): void
    {
        DB::transaction(function () use ($game, $player, $removedByHost) {
            $player->forceFill([
                'left_at' => now(),
                'is_ready' => false,
                'connection_status' => ConnectionStatus::Disconnected,
            ])->save();

            $game->forceFill(['state_version' => $game->state_version + 1])->save();

            $this->activity->log(
                game: $game,
                type: GameActionType::PlayerLeft,
                player: $player,
                message: $removedByHost
                    ? "{$player->nickname} was removed by the host."
                    : ActivityMessage::playerLeft($player),
            );
        });
    }

    /**
     * Toggle a player's ready flag.
     */
    public function setReady(Game $game, GamePlayer $player, bool $ready): void
    {
        $player->forceFill(['is_ready' => $ready, 'last_seen_at' => now()])->save();
        $game->forceFill(['state_version' => $game->state_version + 1])->save();

        $this->activity->log(
            game: $game,
            type: GameActionType::PlayerReady,
            player: $player,
            message: ActivityMessage::playerReady($player, $ready),
        );
    }

    /**
     * Record that a player agreed to the configured punishment.
     */
    public function acknowledgePunishment(Game $game, GamePlayer $player): void
    {
        if ($player->hasAcknowledgedPunishment()) {
            return;
        }

        $player->forceFill(['punishment_acknowledged_at' => now()])->save();
        $game->forceFill(['state_version' => $game->state_version + 1])->save();

        $this->activity->log(
            game: $game,
            type: GameActionType::PunishmentAcknowledged,
            player: $player,
            message: ActivityMessage::punishmentAcknowledged($player),
        );
    }
}
