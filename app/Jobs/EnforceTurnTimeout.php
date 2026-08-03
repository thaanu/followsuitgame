<?php

namespace App\Jobs;

use App\Enums\GameActionType;
use App\Enums\TimeoutAction;
use App\Game\Services\ActivityLogger;
use App\Game\Services\GameplayService;
use App\Game\Services\RuleEngine;
use App\Game\Support\ActivityMessage;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies the configured timeout action when a player's turn deadline passes.
 *
 * The job is scheduled with the state version it was created for, so a player who
 * moves in time turns it into a no-op. The deadline itself lives on the game row,
 * which keeps the timer server-authoritative rather than trusting a browser clock.
 */
class EnforceTurnTimeout implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $gameId,
        public int $stateVersion,
    ) {}

    public function handle(
        GameplayService $gameplay,
        RuleEngine $rules,
        ActivityLogger $activity,
    ): void {
        $game = Game::find($this->gameId);

        if ($game === null || ! $game->status->isPlayable() || $game->is_paused) {
            return;
        }

        // The player already moved, so this timer is stale.
        if ((int) $game->state_version !== $this->stateVersion) {
            return;
        }

        $configuration = $game->ruleConfiguration();

        if (! $configuration->timerEnabled || $game->turn_deadline_at === null) {
            return;
        }

        if ($game->turn_deadline_at->isFuture()) {
            return;
        }

        $player = $game->currentPlayer;

        if ($player === null) {
            return;
        }

        $player->increment('turn_timeout_count');

        $activity->log(
            game: $game,
            type: GameActionType::TurnTimedOut,
            player: $player,
            message: ActivityMessage::timedOut($player),
        );

        $key = "timeout-{$game->id}-{$this->stateVersion}";

        match ($configuration->timeoutAction) {
            TimeoutAction::PlayRandomLegal => $this->playRandomLegalCard($game, $player, $gameplay, $rules, $key),
            TimeoutAction::CollectPile,
            TimeoutAction::SkipTurn => $gameplay->resolveStuckTurn($game, $player, $this->stateVersion, $key),
            // The host decides: leave the turn where it is and let them act.
            TimeoutAction::HostDecides => null,
        };
    }

    private function playRandomLegalCard(
        Game $game,
        GamePlayer $player,
        GameplayService $gameplay,
        RuleEngine $rules,
        string $key,
    ): void {
        $legal = $rules->legalCards($game, $player);

        if ($legal->isEmpty()) {
            $gameplay->resolveStuckTurn($game, $player, $this->stateVersion, $key);

            return;
        }

        /** @var GameCard $card */
        $card = $legal->get(random_int(0, $legal->count() - 1));

        $gameplay->playCard(
            game: $game,
            player: $player,
            cardId: (int) $card->id,
            chosenSuit: $card->suit,
            expectedStateVersion: $this->stateVersion,
            idempotencyKey: $key,
        );
    }
}
