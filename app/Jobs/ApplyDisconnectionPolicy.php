<?php

namespace App\Jobs;

use App\Enums\ConnectionStatus;
use App\Enums\DisconnectBehaviour;
use App\Enums\GameStatus;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\GameJoiningService;
use App\Game\Services\GameplayService;
use App\Game\Services\RuleEngine;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs once a disconnected player's grace period has elapsed and applies the
 * behaviour the host configured.
 */
class ApplyDisconnectionPolicy implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $gameId,
        public int $playerId,
    ) {}

    public function handle(
        GameplayService $gameplay,
        RuleEngine $rules,
        GameJoiningService $joining,
        GameBroadcaster $broadcaster,
    ): void {
        $game = Game::find($this->gameId);
        $player = GamePlayer::find($this->playerId);

        if ($game === null || $player === null || $player->game_id !== $game->id) {
            return;
        }

        // They came back within the grace period.
        if ($player->connection_status !== ConnectionStatus::Disconnected) {
            return;
        }

        if ($game->status === GameStatus::Lobby) {
            $joining->leave($game, $player);
            $broadcaster->playerLeft($game);

            return;
        }

        if (! $game->status->isPlayable() || $game->current_player_id !== $player->id) {
            return;
        }

        $behaviour = $game->ruleConfiguration()->disconnectBehaviour;
        $key = "disconnect-{$game->id}-{$game->state_version}";

        match ($behaviour) {
            // Pausing and host-decides both leave the game where it is on purpose.
            DisconnectBehaviour::PauseGame, DisconnectBehaviour::HostDecides => null,
            DisconnectBehaviour::SkipAfterTimeout => $gameplay->resolveStuckTurn(
                $game, $player, (int) $game->state_version, $key,
            ),
            DisconnectBehaviour::AutoPlay => $this->autoPlay($game, $player, $gameplay, $rules, $key),
        };
    }

    private function autoPlay(
        Game $game,
        GamePlayer $player,
        GameplayService $gameplay,
        RuleEngine $rules,
        string $key,
    ): void {
        $legal = $rules->legalCards($game, $player);

        if ($legal->isEmpty()) {
            $gameplay->resolveStuckTurn($game, $player, (int) $game->state_version, $key);

            return;
        }

        $card = $legal->get(random_int(0, $legal->count() - 1));

        $gameplay->playCard(
            game: $game,
            player: $player,
            cardId: (int) $card->id,
            chosenSuit: $card->suit,
            expectedStateVersion: (int) $game->state_version,
            idempotencyKey: $key,
        );
    }
}
