<?php

namespace App\Game\Services;

use App\Enums\GameActionType;
use App\Enums\GameStatus;
use App\Game\Data\Standing;
use App\Game\Support\ActivityMessage;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameRound;

/**
 * Detects the end of the game, freezes the result, and works out the punishment.
 */
class WinningConditionService
{
    public function __construct(
        private readonly RuleEngine $rules,
        private readonly PunishmentResolver $punishments,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Record that a player has emptied their hand.
     *
     * @return int the position they finished in
     */
    public function markFinished(Game $game, GamePlayer $player): int
    {
        if ($player->finishing_position !== null) {
            return (int) $player->finishing_position;
        }

        $position = $game->activePlayers()->whereNotNull('finishing_position')->count() + 1;

        $player->forceFill(['finishing_position' => $position])->save();

        $this->activity->log(
            game: $game,
            type: GameActionType::PlayerFinished,
            player: $player,
            message: ActivityMessage::playerFinished($player, $position),
            publicPayload: ['position' => $position],
        );

        return $position;
    }

    /**
     * Freeze the game as completed and store the final result.
     *
     * @return array<string, mixed> the results payload
     */
    public function complete(Game $game): array
    {
        // Anybody still holding cards is ranked behind the players who went out.
        foreach ($this->rules->standings($game) as $standing) {
            if ($standing->player->finishing_position === null && $standing->cardsRemaining === 0) {
                $this->markFinished($game, $standing->player);
            }
        }

        $standings = $this->rules->standings($game);
        $winner = $standings[0]->player ?? null;
        $punishment = $this->punishments->resolve($game, $standings);

        $results = $this->buildResults($game, $standings, $punishment);

        $game->forceFill([
            'status' => GameStatus::Completed,
            'completed_at' => now(),
            'current_player_id' => null,
            'turn_deadline_at' => null,
            'state_version' => $game->state_version + 1,
        ])->save();

        GameRound::updateOrCreate(
            ['game_id' => $game->id, 'round_number' => max(1, (int) $game->round_number)],
            ['winner_player_id' => $winner?->id, 'completed_at' => now(), 'result' => $results],
        );

        $this->activity->log(
            game: $game,
            type: GameActionType::GameCompleted,
            message: ActivityMessage::gameCompleted($winner),
            publicPayload: ['winner' => $winner?->public_id],
        );

        if ($punishment !== null && $punishment['player'] instanceof GamePlayer) {
            $configuration = $game->punishmentConfiguration();

            if ($configuration !== null) {
                $this->activity->log(
                    game: $game,
                    type: GameActionType::PunishmentAssigned,
                    player: $punishment['player'],
                    message: ActivityMessage::punishmentAssigned($punishment['player'], $configuration, $punishment['reason']),
                );
            }
        }

        return $results;
    }

    /**
     * The results payload shown on the results screen and stored on the round.
     *
     * @param  array<int, Standing>  $standings
     * @param  array<string, mixed>|null  $punishment
     * @return array<string, mixed>
     */
    public function buildResults(Game $game, array $standings, ?array $punishment): array
    {
        $winner = $standings[0] ?? null;
        $configuration = $game->punishmentConfiguration();

        $duration = $game->started_at !== null
            ? $game->started_at->diffInSeconds($game->completed_at ?? now())
            : 0;

        return [
            'winner' => $winner?->toArray(),
            'shared_win' => $this->rules->hasUnresolvedTie($standings),
            'standings' => array_map(fn (Standing $standing) => $standing->toArray(), $standings),
            'turns_played' => (int) $game->turn_number,
            'rounds_played' => (int) $game->round_number,
            'duration_seconds' => (int) $duration,
            'punishment' => $configuration === null || $punishment === null ? null : [
                ...$configuration->summary(),
                'player' => $punishment['player'] instanceof GamePlayer ? [
                    'player_id' => $punishment['player']->public_id,
                    'nickname' => $punishment['player']->nickname,
                    'initials' => $punishment['player']->initials(),
                ] : null,
                'reason' => $punishment['reason'],
                'tied' => $punishment['tied'],
                'tied_players' => $punishment['tied_players'],
                'pending_host_decision' => $punishment['pending_host_decision'],
            ],
        ];
    }

    /**
     * The stored results for a finished game, rebuilt if they were never persisted.
     *
     * @return array<string, mixed>
     */
    public function results(Game $game): array
    {
        $stored = $game->rounds()
            ->whereNotNull('result')
            ->orderByDesc('round_number')
            ->value('result');

        if (is_array($stored) && $stored !== []) {
            return $stored;
        }

        $standings = $this->rules->standings($game);

        return $this->buildResults($game, $standings, $this->punishments->resolve($game, $standings));
    }
}
