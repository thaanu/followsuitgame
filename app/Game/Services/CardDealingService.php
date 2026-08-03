<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Enums\DealMethod;
use App\Game\Data\RuleConfiguration;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Support\Collection;

/**
 * Writes a shuffled deck into `game_cards` and hands cards to the seated players.
 *
 * @phpstan-import-type DeckCard from DeckService
 */
class CardDealingService
{
    /**
     * Persist the deck and deal it according to the configured deal method.
     *
     * @param  array<int, DeckCard>  $deck
     * @param  Collection<int, GamePlayer>  $players  seated players, in seat order
     */
    public function deal(Game $game, array $deck, Collection $players, RuleConfiguration $rules): void
    {
        $this->persistDeck($game, $deck);

        $cards = $game->cards()->orderBy('original_deal_position')->get();

        $perPlayer = $this->cardsPerPlayer($rules, $cards->count(), $players->count());

        $assignments = [];
        $index = 0;

        // Deal round-robin so each player receives cards from across the shuffled deck,
        // exactly as a physical deal would.
        for ($round = 0; $round < $perPlayer; $round++) {
            foreach ($players as $player) {
                if (! isset($cards[$index])) {
                    break 2;
                }

                $assignments[$player->id][] = $cards[$index]->id;
                $index++;
            }
        }

        foreach ($assignments as $playerId => $cardIds) {
            GameCard::whereIn('id', $cardIds)->update([
                'location' => CardLocation::PlayerHand,
                'player_id' => $playerId,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * How many cards each player receives.
     */
    public function cardsPerPlayer(RuleConfiguration $rules, int $deckSize, int $playerCount): int
    {
        if ($playerCount < 1) {
            return 0;
        }

        return $rules->dealMethod === DealMethod::AllEvenly
            ? intdiv($deckSize, $playerCount)
            : min($rules->fixedCardsPerPlayer, intdiv($deckSize, $playerCount));
    }

    /**
     * @param  array<int, DeckCard>  $deck
     */
    private function persistDeck(Game $game, array $deck): void
    {
        $now = now();

        $rows = [];
        foreach ($deck as $position => $card) {
            $rows[] = [
                'game_id' => $game->id,
                'deck_number' => $card['deck_number'],
                'suit' => $card['suit']->value,
                'rank' => $card['rank']->value,
                'rank_value' => $card['rank_value'],
                'card_code' => $card['card_code'],
                'location' => CardLocation::Deck->value,
                'player_id' => null,
                'pile_position' => null,
                'original_deal_position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            GameCard::insert($chunk);
        }
    }
}
