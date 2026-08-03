<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Enums\CollectedCardsDestination;
use App\Enums\NextLeadRule;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Support\Collection;

/**
 * The lifecycle of the pile in the middle of the table: adding cards to it, moving it
 * to a player when it ends, and deciding who leads the next one.
 */
class PileManager
{
    public function __construct(private readonly TurnManager $turns) {}

    /**
     * Move a card out of a hand and onto the top of the active pile.
     */
    public function addToPile(Game $game, GameCard $card): void
    {
        $position = (int) $game->cards()
            ->where('location', CardLocation::ActivePile)
            ->max('pile_position');

        $card->forceFill([
            'location' => CardLocation::ActivePile,
            'pile_position' => $position + 1,
        ])->save();
    }

    /**
     * Give the whole active pile to a player and clear the table.
     *
     * Where the cards end up is governed by the collected-cards destination rule:
     * they either return to the player's hand, sit in a visible penalty pile, or
     * leave play entirely while still counting against the player.
     *
     * @return int the number of cards collected
     */
    public function collectPile(Game $game, GamePlayer $collector): int
    {
        $pile = $game->activePile();

        if ($pile->isEmpty()) {
            return 0;
        }

        $rules = $game->ruleConfiguration();
        $cardIds = $pile->pluck('id')->all();

        $attributes = match ($rules->collectedCardsDestination) {
            CollectedCardsDestination::Hand => [
                'location' => CardLocation::PlayerHand->value,
                'player_id' => $collector->id,
                'pile_position' => null,
            ],
            CollectedCardsDestination::CollectedArea => [
                'location' => CardLocation::CollectedCards->value,
                'player_id' => $collector->id,
                'pile_position' => null,
            ],
            CollectedCardsDestination::CountOnly => [
                'location' => CardLocation::Discarded->value,
                'player_id' => $collector->id,
                'pile_position' => null,
            ],
        };

        GameCard::whereIn('id', $cardIds)->update([...$attributes, 'updated_at' => now()]);

        $collector->increment('cards_collected_count', count($cardIds));
        $collector->increment('piles_collected_count');
        $collector->refresh();

        $game->active_suit = null;

        return count($cardIds);
    }

    /**
     * Discard the pile without giving it to anybody, used by the "clear the pile"
     * special card effect.
     */
    public function clearPile(Game $game): int
    {
        $pile = $game->activePile();

        if ($pile->isEmpty()) {
            return 0;
        }

        GameCard::whereIn('id', $pile->pluck('id')->all())->update([
            'location' => CardLocation::Discarded->value,
            'player_id' => null,
            'pile_position' => null,
            'updated_at' => now(),
        ]);

        $game->active_suit = null;

        return $pile->count();
    }

    /**
     * Who leads the next pile, per the configured next-lead rule.
     */
    public function nextLeadPlayer(Game $game, GamePlayer $collector, ?GamePlayer $lastFollower): ?GamePlayer
    {
        $rules = $game->ruleConfiguration();

        $candidate = match ($rules->nextLeadRule) {
            NextLeadRule::Collector => $collector,
            NextLeadRule::NextInOrder => $this->turns->playerAfter($game, $collector),
            NextLeadRule::LastFollower => $lastFollower ?? $collector,
        };

        if ($candidate === null) {
            return $this->turns->playerAfter($game, $collector);
        }

        // The chosen player may have just run out of cards; fall through to the next
        // seat that can actually lead.
        $eligible = $this->turns->eligiblePlayers($game);

        if ($eligible->contains(fn (GamePlayer $player) => $player->id === $candidate->id)) {
            return $candidate;
        }

        return $this->turns->playerAfter($game, $candidate);
    }

    /**
     * The player who played the highest-positioned card in the pile before the
     * current one, used by the "last successful follower" lead rule.
     *
     * @param  Collection<int, GameCard>  $pile
     */
    public function lastFollower(Collection $pile): ?GamePlayer
    {
        return $pile->sortByDesc('pile_position')->first()?->player;
    }
}
