<?php

namespace App\Game\Services;

use App\Enums\GameActionType;
use App\Enums\GameStatus;
use App\Enums\RulePreset;
use App\Game\Data\PunishmentConfiguration;
use App\Game\Data\RuleConfiguration;
use App\Game\Support\GameCode;
use App\Game\Support\PlayerToken;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a game room, seats the host, and issues the invitation credentials.
 */
class GameCreationService
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>|null  $punishment
     * @return array{game: Game, host: GamePlayer, token: string}
     */
    public function create(
        string $hostNickname,
        int $maximumPlayers,
        ?string $name,
        RulePreset $preset,
        array $rules,
        ?array $punishment,
    ): array {
        $configuration = RuleConfiguration::fromArray($rules);
        $punishmentConfiguration = PunishmentConfiguration::fromArray($punishment);

        $token = PlayerToken::generate();

        return DB::transaction(function () use (
            $hostNickname,
            $maximumPlayers,
            $name,
            $preset,
            $configuration,
            $punishmentConfiguration,
            $token,
        ) {
            $game = Game::create([
                'public_id' => (string) Str::ulid(),
                'game_code' => $this->uniqueGameCode(),
                'invitation_token' => Str::random(64),
                'name' => $name,
                'status' => GameStatus::Lobby,
                'maximum_players' => $maximumPlayers,
                'rule_preset' => $preset,
                'rules' => $configuration->toArray(),
                'punishment_enabled' => $punishmentConfiguration !== null,
                'punishment' => $punishmentConfiguration?->toArray(),
                'direction' => $configuration->turnDirection,
                'expires_at' => now()->addHours((int) config('followsuit.game_lifetime_hours', 24)),
            ]);

            $host = GamePlayer::create([
                'public_id' => (string) Str::ulid(),
                'game_id' => $game->id,
                'nickname' => $hostNickname,
                'secure_token_hash' => PlayerToken::hash($token),
                'seat_number' => 1,
                'is_host' => true,
                'is_ready' => true,
                'punishment_acknowledged_at' => $punishmentConfiguration !== null ? now() : null,
                'joined_at' => now(),
                'last_seen_at' => now(),
            ]);

            $game->forceFill(['host_player_id' => $host->id])->save();

            $this->activity->log(
                game: $game,
                type: GameActionType::GameCreated,
                player: $host,
                message: "{$host->nickname} created the game.",
            );

            return ['game' => $game->refresh(), 'host' => $host, 'token' => $token];
        });
    }

    /**
     * Replace a finished game's rules while keeping the same lobby, used by rematches
     * that change the rules.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>|null  $punishment
     */
    public function updateSettings(
        Game $game,
        ?string $name,
        int $maximumPlayers,
        RulePreset $preset,
        array $rules,
        ?array $punishment,
    ): Game {
        $configuration = RuleConfiguration::fromArray($rules);
        $punishmentConfiguration = PunishmentConfiguration::fromArray($punishment);

        $game->forceFill([
            'name' => $name,
            'maximum_players' => $maximumPlayers,
            'rule_preset' => $preset,
            'rules' => $configuration->toArray(),
            'punishment_enabled' => $punishmentConfiguration !== null,
            'punishment' => $punishmentConfiguration?->toArray(),
            'direction' => $configuration->turnDirection,
            'state_version' => $game->state_version + 1,
        ])->save();

        // Changing the punishment invalidates every acknowledgement.
        $game->players()->update(['punishment_acknowledged_at' => null]);

        if ($punishmentConfiguration !== null) {
            $game->host?->forceFill(['punishment_acknowledged_at' => now()])->save();
        }

        $this->activity->log(
            game: $game,
            type: GameActionType::SettingsUpdated,
            player: $game->host,
            message: 'The host updated the game settings.',
        );

        return $game->refresh();
    }

    private function uniqueGameCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = GameCode::generate();

            if (! Game::where('game_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to allocate a unique game code.');
    }
}
