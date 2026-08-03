<?php

namespace App\Models;

use App\Enums\CardLocation;
use App\Enums\ConnectionStatus;
use Carbon\CarbonInterface;
use Database\Factories\GamePlayerFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seat at a Follow Suit table.
 *
 * Implements Authenticatable so broadcasting can authorise private channels using
 * the player's browser token instead of a registered user account.
 *
 * @property int $id
 * @property string $public_id
 * @property int $game_id
 * @property string $nickname
 * @property string $secure_token_hash
 * @property int $seat_number
 * @property bool $is_host
 * @property bool $is_ready
 * @property CarbonInterface|null $punishment_acknowledged_at
 * @property ConnectionStatus $connection_status
 * @property CarbonInterface|null $last_seen_at
 * @property CarbonInterface|null $disconnected_at
 * @property int $turn_timeout_count
 * @property int $cards_collected_count
 * @property int $piles_collected_count
 * @property int $failed_follow_suit_count
 * @property int|null $finishing_position
 * @property CarbonInterface|null $joined_at
 * @property CarbonInterface|null $left_at
 * @property-read Game|null $game
 * @property-read Collection<int, GameCard> $cards
 */
#[UseFactory(GamePlayerFactory::class)]
class GamePlayer extends Model implements Authenticatable
{
    /** @use HasFactory<GamePlayerFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['secure_token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_ready' => 'boolean',
            'connection_status' => ConnectionStatus::class,
            'punishment_acknowledged_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
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
     * @return HasMany<GameCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(GameCard::class, 'player_id');
    }

    /**
     * The cards this player is holding, sorted for display.
     *
     * @return Collection<int, GameCard>
     */
    public function hand(): Collection
    {
        return $this->cards()
            ->where('location', CardLocation::PlayerHand)
            ->orderBy('suit')
            ->orderBy('rank_value')
            ->get();
    }

    public function handCount(): int
    {
        return $this->cards()->where('location', CardLocation::PlayerHand)->count();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function hasAcknowledgedPunishment(): bool
    {
        return $this->punishment_acknowledged_at !== null;
    }

    public function isConnected(): bool
    {
        return $this->connection_status === ConnectionStatus::Connected;
    }

    public function hasLeft(): bool
    {
        return $this->left_at !== null;
    }

    /**
     * The two-letter avatar shown in the lobby and around the table.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->nickname)) ?: [];
        $letters = array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_filter($words),
        );

        return mb_substr(implode('', array_slice($letters, 0, 2)) ?: '?', 0, 2);
    }

    // Authenticatable — the player identity used for broadcast channel authorisation.

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'secure_token_hash';
    }

    public function getAuthPassword(): string
    {
        return (string) $this->secure_token_hash;
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void
    {
        // Players do not use remember tokens.
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
