<?php

namespace App\Game\Services;

use App\Enums\AceRule;
use App\Enums\CardLocation;
use App\Enums\CardRank;
use App\Enums\CardSuit;
use App\Enums\PileEndCondition;
use App\Enums\RoundWinner;
use App\Enums\SpecialCardEffect;
use App\Enums\StartingPlayerMethod;
use App\Enums\TieBreakRule;
use App\Enums\WinningCondition;
use App\Game\Data\RuleConfiguration;
use App\Game\Data\Standing;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Support\Collection;

/**
 * Every rule decision in the game, driven entirely by the game's saved
 * {@see RuleConfiguration}. Nothing here writes to the database.
 */
class RuleEngine
{
    /**
     * The cards this player may legally play right now.
     *
     * A player leading a new pile may play anything. Otherwise they must follow the
     * active suit, with two configurable exceptions: an Ace is always playable when
     * "Ace changes the active suit" is enabled, and a Joker is always playable
     * because it has no suit of its own.
     *
     * @param  Collection<int, GameCard>|null  $hand  pass a pre-loaded hand to avoid a query
     * @param  Collection<int, GameCard>|null  $pile  pass a pre-loaded pile to avoid a query
     * @return Collection<int, GameCard>
     */
    public function legalCards(Game $game, GamePlayer $player, ?Collection $hand = null, ?Collection $pile = null): Collection
    {
        $rules = $game->ruleConfiguration();
        $hand ??= $player->hand();
        $pile ??= $game->activePile();

        if ($pile->isEmpty() || $game->active_suit === null) {
            return $hand->values();
        }

        $activeSuit = $game->active_suit;

        return $hand->filter(function (GameCard $card) use ($activeSuit, $rules): bool {
            if ($card->suit === $activeSuit) {
                return true;
            }

            if ($card->rank === CardRank::Joker) {
                return true;
            }

            return $card->rank === CardRank::Ace
                && $rules->isEffectEnabled(SpecialCardEffect::AceChangesSuit);
        })->values();
    }

    /**
     * @param  Collection<int, GameCard>|null  $hand
     * @param  Collection<int, GameCard>|null  $pile
     */
    public function canFollowSuit(Game $game, GamePlayer $player, ?Collection $hand = null, ?Collection $pile = null): bool
    {
        return $this->legalCards($game, $player, $hand, $pile)->isNotEmpty();
    }

    /**
     * Whether the player is allowed to collect the pile even though they can follow suit.
     */
    public function mayVoluntarilyCollect(Game $game, GamePlayer $player): bool
    {
        $rules = $game->ruleConfiguration();

        if (! $rules->allowVoluntaryCollect) {
            return false;
        }

        return $game->activePile()->isNotEmpty();
    }

    /**
     * Whether the pile is complete after the card that has just been played.
     *
     * The "cannot follow suit" condition is not decided here: that pile only ends when
     * a player fails to follow, which is handled by the unable-to-follow flow.
     */
    public function pileShouldEnd(Game $game, int $cardsInPile, ?GameCard $lastCard = null): bool
    {
        $rules = $game->ruleConfiguration();

        return match ($rules->pileEndCondition) {
            PileEndCondition::CannotFollowSuit => false,
            PileEndCondition::EveryPlayerPlayed => $cardsInPile >= max(2, $this->playersInPlay($game)->count()),
            PileEndCondition::FixedCardCount => $cardsInPile >= $rules->pileFixedCardCount,
            PileEndCondition::SpecialCard => $lastCard !== null
                && in_array(SpecialCardEffect::ClearPile, $this->effectsFor($game, $lastCard), true),
        };
    }

    /**
     * Which player wins a pile that ended because it was complete, rather than because
     * somebody could not follow suit.
     *
     * @param  Collection<int, GameCard>  $pile
     */
    public function pileWinner(Game $game, Collection $pile): ?GamePlayer
    {
        $rules = $game->ruleConfiguration();

        $contenders = $pile->filter(
            fn (GameCard $card) => $card->player_id !== null && $card->suit === $game->active_suit
        );

        if ($contenders->isEmpty()) {
            $contenders = $pile->filter(fn (GameCard $card) => $card->player_id !== null);
        }

        if ($contenders->isEmpty()) {
            return null;
        }

        if ($rules->aceRule === AceRule::RankIrrelevant) {
            // Rank is meaningless, so the player who led the pile takes it.
            $winningCard = $contenders->sortBy('pile_position')->first();
        } else {
            $winningCard = $rules->roundWinner === RoundWinner::HighestCard
                ? $contenders->sortByDesc('rank_value')->first()
                : $contenders->sortBy('rank_value')->first();
        }

        return $winningCard?->player;
    }

    /**
     * The special card effects triggered by playing this card.
     *
     * @return array<int, SpecialCardEffect>
     */
    public function effectsFor(Game $game, GameCard $card): array
    {
        $rules = $game->ruleConfiguration();
        $effects = [];

        foreach ($rules->enabledEffects() as $effect) {
            $trigger = $effect === SpecialCardEffect::ClearPile
                ? $rules->clearPileRank
                : $effect->trigger();

            if ($trigger !== null && $card->rank === $trigger) {
                $effects[] = $effect;
            }
        }

        return $effects;
    }

    /**
     * Whether playing this card requires the player to nominate a new active suit.
     */
    public function requiresSuitChoice(Game $game, GameCard $card): bool
    {
        $effects = $this->effectsFor($game, $card);

        return in_array(SpecialCardEffect::AceChangesSuit, $effects, true)
            || in_array(SpecialCardEffect::JokerWildSuit, $effects, true);
    }

    /**
     * The seat that starts the game, resolved from the configured method.
     *
     * @param  Collection<int, GamePlayer>  $players
     */
    public function startingPlayer(Game $game, Collection $players): GamePlayer
    {
        $rules = $game->ruleConfiguration();

        return match ($rules->startingPlayerMethod) {
            StartingPlayerMethod::Host => $players->firstWhere('is_host', true) ?? $players->first(),
            StartingPlayerMethod::Random => $players->get(random_int(0, max(0, $players->count() - 1))) ?? $players->first(),
            StartingPlayerMethod::SpecificCard => $this->holderOf($game, $players, $rules->startingCardCode),
        };
    }

    /**
     * Players who are still seated and still hold cards.
     *
     * @return Collection<int, GamePlayer>
     */
    public function playersInPlay(Game $game): Collection
    {
        return $game->activePlayers()
            ->whereHas('cards', fn ($query) => $query->where('location', CardLocation::PlayerHand))
            ->orderBy('seat_number')
            ->get();
    }

    /**
     * Whether the configured winning condition has been met.
     */
    public function isComplete(Game $game): bool
    {
        $rules = $game->ruleConfiguration();
        $seated = $game->activePlayers()->get();

        if ($seated->count() < 2) {
            return true;
        }

        $stillHolding = $this->playersInPlay($game);

        return match ($rules->winningCondition) {
            WinningCondition::FirstToEmptyHand => $stillHolding->count() < $seated->count(),
            WinningCondition::LastRemainingLoses,
            WinningCondition::PlayUntilOneHasCards => $stillHolding->count() <= 1,
            WinningCondition::FewestCollectedCards,
            WinningCondition::FewestCollectedPiles => $stillHolding->count() <= 1,
            WinningCondition::FixedRounds => $game->round_number >= $rules->roundLimit
                && $stillHolding->count() <= 1,
        };
    }

    /**
     * Whether the current round is over but more rounds remain to be played.
     */
    public function roundIsComplete(Game $game): bool
    {
        $rules = $game->ruleConfiguration();

        if ($rules->winningCondition !== WinningCondition::FixedRounds) {
            return false;
        }

        return $this->playersInPlay($game)->count() <= 1;
    }

    /**
     * The final table, best first, with ties resolved by the configured rule.
     *
     * @return array<int, Standing>
     */
    public function standings(Game $game): array
    {
        $rules = $game->ruleConfiguration();
        $players = $game->activePlayers()->get();

        $rows = $players->map(function (GamePlayer $player) {
            return [
                'player' => $player,
                'cards_remaining' => $player->handCount(),
                'cards_collected' => (int) $player->cards_collected_count,
                'piles_collected' => (int) $player->piles_collected_count,
                'failed_follow_suit' => (int) $player->failed_follow_suit_count,
                'finishing_position' => $player->finishing_position,
            ];
        })->values()->all();

        usort($rows, fn (array $a, array $b) => $this->compare($a, $b, $rules->winningCondition, $rules->tieBreakRule));

        $standings = [];
        $rank = 0;
        $previous = null;

        foreach ($rows as $index => $row) {
            $tied = $previous !== null
                && $rules->tieBreakRule === TieBreakRule::Shared
                && $this->compare($previous, $row, $rules->winningCondition, $rules->tieBreakRule) === 0;

            if (! $tied) {
                $rank = $index + 1;
            }

            $standings[] = new Standing(
                player: $row['player'],
                position: $rank,
                cardsRemaining: $row['cards_remaining'],
                cardsCollected: $row['cards_collected'],
                pilesCollected: $row['piles_collected'],
                failedFollowSuit: $row['failed_follow_suit'],
                tied: $tied,
            );

            $previous = $row;
        }

        return $standings;
    }

    /**
     * Whether the standings still contain an unresolved tie for first place.
     *
     * @param  array<int, Standing>  $standings
     */
    public function hasUnresolvedTie(array $standings): bool
    {
        return count(array_filter($standings, fn (Standing $standing) => $standing->position === 1)) > 1;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compare(array $a, array $b, WinningCondition $condition, TieBreakRule $tieBreak): int
    {
        $primary = match ($condition) {
            WinningCondition::FirstToEmptyHand,
            WinningCondition::LastRemainingLoses,
            WinningCondition::PlayUntilOneHasCards,
            WinningCondition::FixedRounds => $this->comparePositions($a, $b),
            WinningCondition::FewestCollectedCards => $a['cards_collected'] <=> $b['cards_collected'],
            WinningCondition::FewestCollectedPiles => $a['piles_collected'] <=> $b['piles_collected'],
        };

        if ($primary !== 0) {
            return $primary;
        }

        return match ($tieBreak) {
            TieBreakRule::FewerCollectedCards => $a['cards_collected'] <=> $b['cards_collected'],
            TieBreakRule::FewerCollectedPiles => $a['piles_collected'] <=> $b['piles_collected'],
            TieBreakRule::Shared, TieBreakRule::TieBreakerRound => 0,
        };
    }

    /**
     * Players who emptied their hand are ranked by when they did so; anybody still
     * holding cards ranks behind them, fewest cards first.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function comparePositions(array $a, array $b): int
    {
        $aFinished = $a['finishing_position'] !== null;
        $bFinished = $b['finishing_position'] !== null;

        if ($aFinished && $bFinished) {
            return $a['finishing_position'] <=> $b['finishing_position'];
        }

        if ($aFinished !== $bFinished) {
            return $aFinished ? -1 : 1;
        }

        return $a['cards_remaining'] <=> $b['cards_remaining'];
    }

    /**
     * @param  Collection<int, GamePlayer>  $players
     */
    private function holderOf(Game $game, Collection $players, string $cardCode): GamePlayer
    {
        $card = $game->cards()
            ->where('card_code', $cardCode)
            ->where('location', CardLocation::PlayerHand)
            ->first();

        if ($card?->player_id === null) {
            return $players->firstWhere('is_host', true) ?? $players->first();
        }

        return $players->firstWhere('id', $card->player_id)
            ?? $players->firstWhere('is_host', true)
            ?? $players->first();
    }

    /**
     * The suit a wild card leaves behind when the player did not nominate one.
     */
    public function fallbackSuit(GameCard $card, ?CardSuit $chosen): CardSuit
    {
        if ($chosen !== null && $chosen !== CardSuit::None) {
            return $chosen;
        }

        return $card->suit === CardSuit::None ? CardSuit::Hearts : $card->suit;
    }
}
