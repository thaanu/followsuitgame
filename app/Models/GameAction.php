<?php

namespace App\Models;

use App\Enums\GameActionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property int|null $player_id
 * @property GameActionType $action_type
 * @property int|null $card_id
 * @property int $turn_number
 * @property int $state_version
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $public_payload
 * @property array<string, mixed>|null $private_payload
 * @property CarbonInterface|null $created_at
 */
class GameAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_type' => GameActionType::class,
            'public_payload' => 'array',
            'private_payload' => 'array',
            'created_at' => 'datetime',
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
    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'player_id');
    }

    /**
     * The activity feed entry for this action. Private payloads never leave the server.
     *
     * @return array{id: int, type: string, message: string, at: string|null}
     */
    public function toActivityArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->action_type->value,
            'message' => (string) ($this->public_payload['message'] ?? ''),
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
