<?php

namespace App\Models;

use App\Enums\CardLocation;
use App\Enums\CardSuit;
use App\Enums\GameStatus;
use App\Enums\RulePreset;
use App\Enums\TurnDirection;
use App\Game\Data\PunishmentConfiguration;
use App\Game\Data\RuleConfiguration;
use App\Policies\GamePolicy;
use Carbon\CarbonInterface;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $game_code
 * @property string $invitation_token
 * @property string|null $name
 * @property int|null $host_player_id
 * @property int|null $current_player_id
 * @property int|null $starting_player_id
 * @property int|null $rematch_game_id
 * @property GameStatus $status
 * @property int $maximum_players
 * @property RulePreset $rule_preset
 * @property array<string, mixed> $rules
 * @property bool $punishment_enabled
 * @property array<string, mixed>|null $punishment
 * @property CardSuit|null $active_suit
 * @property TurnDirection $direction
 * @property int $round_number
 * @property int $turn_number
 * @property int $state_version
 * @property CarbonInterface|null $turn_deadline_at
 * @property bool $pending_extra_turn
 * @property bool $is_paused
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $cancelled_at
 * @property CarbonInterface|null $expires_at
 * @property-read Collection<int, GamePlayer> $players
 * @property-read Collection<int, GameCard> $cards
 * @property-read GamePlayer|null $host
 * @property-read GamePlayer|null $currentPlayer
 * @property-read GamePlayer|null $startingPlayer
 */
#[UseFactory(GameFactory::class)]
#[UsePolicy(GamePolicy::class)]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['invitation_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'rule_preset' => RulePreset::class,
            'rules' => 'array',
            'punishment' => 'array',
            'punishment_enabled' => 'boolean',
            'active_suit' => CardSuit::class,
            'direction' => TurnDirection::class,
            'pending_extra_turn' => 'boolean',
            'is_paused' => 'boolean',
            'turn_deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<GamePlayer, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class)->orderBy('seat_number');
    }

    /**
     * Players still taking part, ordered by seat.
     *
     * @return HasMany<GamePlayer, $this>
     */
    public function activePlayers(): HasMany
    {
        return $this->players()->whereNull('left_at');
    }

    /**
     * @return HasMany<GameCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(GameCard::class);
    }

    /**
     * @return HasMany<GameAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(GameAction::class);
    }

    /**
     * @return HasMany<GameRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }

    /**
     * @return BelongsTo<GamePlayer, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'host_player_id');
    }

    /**
     * @return BelongsTo<GamePlayer, $this>
     */
    public function currentPlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'current_player_id');
    }

    /**
     * @return BelongsTo<GamePlayer, $this>
     */
    public function startingPlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'starting_player_id');
    }

    public function ruleConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromArray($this->rules ?? []);
    }

    public function punishmentConfiguration(): ?PunishmentConfiguration
    {
        if (! $this->punishment_enabled) {
            return null;
        }

        return PunishmentConfiguration::fromArray($this->punishment);
    }

    /**
     * The cards currently face up in the middle of the table, in play order.
     *
     * @return Collection<int, GameCard>
     */
    public function activePile(): Collection
    {
        return $this->cards()
            ->where('location', CardLocation::ActivePile)
            ->orderBy('pile_position')
            ->get();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function invitationUrl(): string
    {
        return route('games.join.show', ['code' => $this->game_code, 'token' => $this->invitation_token]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<Game>  $query
     */
    public function scopeAbandoned(Builder $query): void
    {
        $query->whereIn('status', [GameStatus::Lobby, GameStatus::InProgress])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
