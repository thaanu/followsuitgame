<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GamePlayer>
 */
class GamePlayerFactory extends Factory
{
    protected $model = GamePlayer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'public_id' => (string) Str::ulid(),
            'game_id' => Game::factory(),
            'nickname' => fake()->unique()->firstName(),
            'secure_token_hash' => hash('sha256', $token),
            'seat_number' => 1,
            'is_host' => false,
            'is_ready' => false,
            'connection_status' => ConnectionStatus::Connected,
            'last_seen_at' => now(),
            'joined_at' => now(),
        ];
    }

    public function host(): self
    {
        return $this->state(fn () => [
            'is_host' => true,
            'is_ready' => true,
            'seat_number' => 1,
        ]);
    }

    public function ready(): self
    {
        return $this->state(fn () => ['is_ready' => true]);
    }

    public function seat(int $seat): self
    {
        return $this->state(fn () => ['seat_number' => $seat]);
    }

    public function withToken(string $token): self
    {
        return $this->state(fn () => ['secure_token_hash' => hash('sha256', $token)]);
    }

    public function disconnected(): self
    {
        return $this->state(fn () => [
            'connection_status' => ConnectionStatus::Disconnected,
            'disconnected_at' => now(),
        ]);
    }
}
