<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property int $round_number
 * @property int|null $starting_player_id
 * @property int|null $winner_player_id
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property array<string, mixed>|null $result
 */
class GameRound extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<GamePlayer, $this>
     */
    public function startingPlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'starting_player_id');
    }

    /**
     * @return BelongsTo<GamePlayer, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'winner_player_id');
    }
}
