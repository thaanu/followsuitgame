<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Enums\RulePreset;
use App\Enums\TurnDirection;
use App\Game\Data\RuleConfiguration;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'game_code' => Str::upper(Str::random(6)),
            'invitation_token' => Str::random(64),
            'name' => fake()->words(2, true),
            'status' => GameStatus::Lobby,
            'maximum_players' => 4,
            'rule_preset' => RulePreset::Classic,
            'rules' => RuleConfiguration::presetRules(RulePreset::Classic),
            'punishment_enabled' => false,
            'punishment' => null,
            'direction' => TurnDirection::Clockwise,
            'round_number' => 0,
            'turn_number' => 0,
            'state_version' => 0,
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function withRules(array $rules): self
    {
        return $this->state(fn (array $attributes) => [
            'rules' => array_replace_recursive($attributes['rules'] ?? RuleConfiguration::defaults(), $rules),
            'rule_preset' => RulePreset::Custom,
        ]);
    }

    public function preset(RulePreset $preset): self
    {
        return $this->state(fn () => [
            'rule_preset' => $preset,
            'rules' => RuleConfiguration::presetRules($preset),
        ]);
    }

    public function inProgress(): self
    {
        return $this->state(fn () => [
            'status' => GameStatus::InProgress,
            'started_at' => now(),
            'round_number' => 1,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => GameStatus::Completed,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => GameStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
