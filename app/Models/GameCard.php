<?php

namespace App\Models;

use App\Enums\CardLocation;
use App\Enums\CardRank;
use App\Enums\CardSuit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property int $deck_number
 * @property CardSuit $suit
 * @property CardRank $rank
 * @property int $rank_value
 * @property string $card_code
 * @property CardLocation $location
 * @property int|null $player_id
 * @property int|null $pile_position
 * @property int|null $original_deal_position
 * @property-read Game|null $game
 * @property-read GamePlayer|null $player
 */
class GameCard extends Model
{
    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['original_deal_position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suit' => CardSuit::class,
            'rank' => CardRank::class,
            'location' => CardLocation::class,
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

    public function isJoker(): bool
    {
        return $this->rank === CardRank::Joker;
    }

    public function label(): string
    {
        return $this->isJoker()
            ? 'Joker'
            : "{$this->rank->label()} of {$this->suit->label()}";
    }

    /**
     * The payload sent to a client that is allowed to see this card's identity.
     *
     * @return array{id: int, code: string, suit: string, rank: string, rank_value: int, label: string, is_red: bool}
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->card_code,
            'suit' => $this->suit->value,
            'rank' => $this->rank->value,
            'rank_value' => (int) $this->rank_value,
            'label' => $this->label(),
            'is_red' => $this->suit->isRed(),
        ];
    }
}
