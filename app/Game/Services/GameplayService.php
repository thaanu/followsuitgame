<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Enums\CardSuit;
use App\Enums\GameActionType;
use App\Enums\PileEndCondition;
use App\Enums\UnableToFollowAction;
use App\Enums\WinningCondition;
use App\Game\Data\MoveResult;
use App\Game\Exceptions\GameException;
use App\Game\Support\ActivityMessage;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\DB;

/**
 * Turn processing: the single place a game's state is allowed to move forward.
 *
 * Every public method runs inside a transaction that takes a row lock on the game,
 * re-validates against the freshly locked state, and finishes by bumping
 * `state_version`, so two requests for the same turn can never both succeed.
 */
class GameplayService
{
    public function __construct(
        private readonly MoveValidationService $validator,
        private readonly RuleEngine $rules,
        private readonly TurnManager $turns,
        private readonly PileManager $piles,
        private readonly SpecialCardEffectManager $effects,
        private readonly WinningConditionService $winning,
        private readonly GameStartService $starter,
        private readonly ActivityLogger $activity,
        private readonly GameBroadcaster $broadcaster,
    ) {}

    /**
     * Play a card from the current player's hand.
     */
    public function playCard(
        Game $game,
        GamePlayer $player,
        int $cardId,
        ?CardSuit $chosenSuit = null,
        ?int $expectedStateVersion = null,
        ?string $idempotencyKey = null,
    ): MoveResult {
        $result = DB::transaction(function () use ($game, $player, $cardId, $chosenSuit, $expectedStateVersion, $idempotencyKey) {
            [$locked, $lockedPlayer] = $this->lock($game, $player);

            $this->validator->assertCanAct($locked, $lockedPlayer, $expectedStateVersion, $idempotencyKey);

            $card = $this->validator->resolveOwnedCard($locked, $lockedPlayer, $cardId);
            $this->validator->assertCardIsLegal($locked, $lockedPlayer, $card);

            $result = new MoveResult;
            $pileWasEmpty = $locked->activePile()->isEmpty();

            $this->piles->addToPile($locked, $card);

            // Leading a pile sets the suit everyone else must follow.
            if ($pileWasEmpty && $card->suit !== CardSuit::None) {
                $locked->active_suit = $card->suit;
            }

            $this->activity->log(
                game: $locked,
                type: GameActionType::CardPlayed,
                player: $lockedPlayer,
                message: ActivityMessage::cardPlayed($lockedPlayer, $card),
                publicPayload: ['card' => $card->toClientArray()],
                card: $card,
                idempotencyKey: $idempotencyKey,
            );

            $context = $this->effects->apply($locked, $lockedPlayer, $card, $chosenSuit);

            foreach ($context->messages as $message) {
                $this->activity->log(
                    game: $locked,
                    type: GameActionType::SuitChanged,
                    player: $lockedPlayer,
                    message: $message,
                );
                $result->message($message);
            }

            $result->needsHandUpdate($lockedPlayer);

            $handCount = $lockedPlayer->handCount();

            if ($context->clearsPile) {
                $cleared = $this->piles->clearPile($locked);
                $this->activity->log(
                    game: $locked,
                    type: GameActionType::PileCleared,
                    player: $lockedPlayer,
                    message: ActivityMessage::pileCleared($cleared),
                );
                $result->pileCollected = true;
            } else {
                $this->resolveCompletedPile($locked, $result);
            }

            if ($handCount === 0) {
                $this->winning->markFinished($locked, $lockedPlayer);
                $result->playerFinished = true;
            } elseif ($handCount === 1) {
                $this->activity->log(
                    game: $locked,
                    type: GameActionType::CardPlayed,
                    player: $lockedPlayer,
                    message: ActivityMessage::cardsRemaining($lockedPlayer, 1),
                );
            }

            $this->advance($locked, $lockedPlayer, $result, $context->extraSkips, $context->grantsExtraTurn && $handCount > 0);

            $this->commit($locked);

            return [$result, $locked];
        });

        [$moveResult, $locked] = $result;

        $this->broadcaster->broadcastMove($locked, $moveResult);

        return $moveResult;
    }

    /**
     * The turn action for a player who cannot follow suit, and the voluntary
     * collect action for a player who can but chooses not to.
     */
    public function resolveStuckTurn(
        Game $game,
        GamePlayer $player,
        ?int $expectedStateVersion = null,
        ?string $idempotencyKey = null,
    ): MoveResult {
        $result = DB::transaction(function () use ($game, $player, $expectedStateVersion, $idempotencyKey) {
            [$locked, $lockedPlayer] = $this->lock($game, $player);

            $this->validator->assertCanAct($locked, $lockedPlayer, $expectedStateVersion, $idempotencyKey);
            $this->validator->assertMayCollect($locked, $lockedPlayer);

            $result = new MoveResult;
            $rules = $locked->ruleConfiguration();
            $couldFollow = $this->rules->canFollowSuit($locked, $lockedPlayer);

            if (! $couldFollow) {
                $lockedPlayer->increment('failed_follow_suit_count');
                $lockedPlayer->refresh();
            }

            // A voluntary collect always collects, whatever the unable-to-follow rule says.
            $action = $couldFollow ? UnableToFollowAction::CollectPile : $rules->unableToFollowAction;

            match ($action) {
                UnableToFollowAction::CollectPile => $this->collect($locked, $lockedPlayer, $result, $idempotencyKey),
                UnableToFollowAction::DrawCards => $this->draw($locked, $lockedPlayer, $result, $idempotencyKey),
                UnableToFollowAction::SkipTurn => $this->skip($locked, $lockedPlayer, $result, $idempotencyKey),
            };

            $this->commit($locked);

            return [$result, $locked];
        });

        [$moveResult, $locked] = $result;

        $this->broadcaster->broadcastMove($locked, $moveResult);

        return $moveResult;
    }

    /**
     * Collect the pile, hand the lead to whoever the rules say, and check for the end
     * of the game.
     */
    private function collect(Game $game, GamePlayer $player, MoveResult $result, ?string $idempotencyKey): void
    {
        $pile = $game->activePile();
        $suit = $game->active_suit;
        $lastFollower = $this->piles->lastFollower($pile);

        $collected = $this->piles->collectPile($game, $player);

        $this->activity->log(
            game: $game,
            type: GameActionType::PileCollected,
            player: $player,
            message: $suit !== null
                ? ActivityMessage::couldNotFollow($player, $suit, $collected)
                : ActivityMessage::collectedPile($player, $collected),
            publicPayload: ['cards_collected' => $collected],
            idempotencyKey: $idempotencyKey,
        );

        $result->pileCollected = true;
        $result->needsHandUpdate($player);

        if ($lastFollower !== null) {
            $result->needsHandUpdate($lastFollower);
        }

        $lead = $this->piles->nextLeadPlayer($game, $player, $lastFollower);

        $this->finishTurn($game, $lead, $result);
    }

    /**
     * Draw the configured number of cards. If the deck cannot cover the draw the
     * player collects the pile instead, so the turn always resolves.
     */
    private function draw(Game $game, GamePlayer $player, MoveResult $result, ?string $idempotencyKey): void
    {
        $rules = $game->ruleConfiguration();
        $drawn = $this->starter->drawCards($game, $player, $rules->drawCardCount);

        if ($drawn === 0) {
            $this->collect($game, $player, $result, $idempotencyKey);

            return;
        }

        $this->activity->log(
            game: $game,
            type: GameActionType::CardsDrawn,
            player: $player,
            message: ActivityMessage::drewCards($player, $drawn),
            publicPayload: ['cards_drawn' => $drawn],
            idempotencyKey: $idempotencyKey,
        );

        $result->needsHandUpdate($player);

        // Drawing into a playable card keeps the turn with the same player.
        if ($this->rules->canFollowSuit($game, $player)) {
            $this->turns->assignTurn($game, $player);

            return;
        }

        $this->finishTurn($game, $this->turns->playerAfter($game, $player), $result);
    }

    private function skip(Game $game, GamePlayer $player, MoveResult $result, ?string $idempotencyKey): void
    {
        $this->activity->log(
            game: $game,
            type: GameActionType::TurnSkipped,
            player: $player,
            message: ActivityMessage::turnSkipped($player),
            idempotencyKey: $idempotencyKey,
        );

        $this->finishTurn($game, $this->turns->playerAfter($game, $player), $result);
    }

    /**
     * Give a completed pile to its winner, when the pile ends by card count rather
     * than because somebody was stuck.
     */
    private function resolveCompletedPile(Game $game, MoveResult $result): void
    {
        $rules = $game->ruleConfiguration();

        if ($rules->pileEndCondition === PileEndCondition::CannotFollowSuit) {
            return;
        }

        $pile = $game->activePile();

        if (! $this->rules->pileShouldEnd($game, $pile->count(), $pile->last())) {
            return;
        }

        $winner = $this->rules->pileWinner($game, $pile);

        if ($winner === null) {
            return;
        }

        $collected = $this->piles->collectPile($game, $winner);

        $this->activity->log(
            game: $game,
            type: GameActionType::PileCollected,
            player: $winner,
            message: ActivityMessage::wonPile($winner, $collected),
            publicPayload: ['cards_collected' => $collected],
        );

        $result->pileCollected = true;
        $result->needsHandUpdate($winner);
    }

    /**
     * Move the turn on after a card was played.
     */
    private function advance(Game $game, GamePlayer $player, MoveResult $result, int $extraSkips, bool $extraTurn): void
    {
        if ($this->finishIfComplete($game, $result)) {
            return;
        }

        if ($extraTurn) {
            $this->turns->assignTurn($game, $player);

            return;
        }

        $skipped = $extraSkips > 0
            ? $this->turns->playerAfter($game, $player)
            : null;

        if ($skipped !== null) {
            $this->activity->log(
                game: $game,
                type: GameActionType::TurnSkipped,
                player: $skipped,
                message: ActivityMessage::skippedByQueen($skipped),
            );
        }

        $this->turns->assignTurn($game, $this->turns->playerAfter($game, $player, 1 + $extraSkips));
    }

    /**
     * Hand the turn to a specific player, unless the game or round has ended.
     */
    private function finishTurn(Game $game, ?GamePlayer $next, MoveResult $result): void
    {
        if ($this->finishIfComplete($game, $result)) {
            return;
        }

        $this->turns->assignTurn($game, $next ?? $this->turns->eligiblePlayers($game)->first());
    }

    /**
     * End the game, or start the next round of a multi-round game.
     */
    private function finishIfComplete(Game $game, MoveResult $result): bool
    {
        $rules = $game->ruleConfiguration();

        if ($rules->winningCondition === WinningCondition::FixedRounds && $this->rules->roundIsComplete($game)) {
            if ($game->round_number < $rules->roundLimit) {
                $this->starter->startNextRound($game);
                $result->roundCompleted = true;

                foreach ($game->activePlayers()->get() as $player) {
                    $result->needsHandUpdate($player);
                }

                return true;
            }
        }

        if (! $this->rules->isComplete($game)) {
            return false;
        }

        $this->winning->complete($game);
        $result->gameCompleted = true;

        return true;
    }

    /**
     * Persist the mutated game row and bump the optimistic concurrency token.
     */
    private function commit(Game $game): void
    {
        $game->state_version = $game->state_version + 1;
        $game->save();
    }

    /**
     * Take the row lock and reload both the game and the acting player inside it.
     *
     * @return array{0: Game, 1: GamePlayer}
     */
    private function lock(Game $game, GamePlayer $player): array
    {
        /** @var Game $locked */
        $locked = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

        /** @var GamePlayer|null $lockedPlayer */
        $lockedPlayer = GamePlayer::whereKey($player->id)->where('game_id', $locked->id)->first();

        if ($lockedPlayer === null) {
            throw GameException::notInGame();
        }

        return [$locked, $lockedPlayer];
    }

    /**
     * The cards the given player may play right now, for the private state payload.
     *
     * @return array<int, int>
     */
    public function legalCardIds(Game $game, GamePlayer $player): array
    {
        if ($game->current_player_id !== $player->id || ! $game->status->isPlayable()) {
            return [];
        }

        return $this->rules->legalCards($game, $player)
            ->map(fn (GameCard $card) => (int) $card->id)
            ->all();
    }

    /**
     * Whether the player currently has no legal card and must resolve their turn
     * through the unable-to-follow action.
     */
    public function isStuck(Game $game, GamePlayer $player): bool
    {
        if ($game->current_player_id !== $player->id || ! $game->status->isPlayable()) {
            return false;
        }

        if ($player->cards()->where('location', CardLocation::PlayerHand)->doesntExist()) {
            return false;
        }

        return ! $this->rules->canFollowSuit($game, $player);
    }
}
