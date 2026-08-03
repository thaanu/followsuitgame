<?php

namespace App\Console\Commands;

use App\Enums\ConnectionStatus;
use App\Enums\GameActionType;
use App\Enums\GameStatus;
use App\Game\Services\ActivityLogger;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\ReconnectionService;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Console\Command;

class CleanupAbandonedGames extends Command
{
    protected $signature = 'games:cleanup
                            {--purge : Also delete completed and cancelled games past their retention window}';

    protected $description = 'Cancel expired games, mark silent players as disconnected, and purge old records';

    public function handle(
        ActivityLogger $activity,
        GameBroadcaster $broadcaster,
        ReconnectionService $reconnection,
    ): int {
        $cancelled = $this->cancelExpiredGames($activity, $broadcaster);
        $disconnected = $this->markSilentPlayers($reconnection);
        $purged = $this->option('purge') ? $this->purgeOldGames() : 0;

        $this->components->info("Cancelled {$cancelled} expired game(s).");
        $this->components->info("Marked {$disconnected} silent player(s) as disconnected.");

        if ($this->option('purge')) {
            $this->components->info("Purged {$purged} finished game(s).");
        }

        return self::SUCCESS;
    }

    private function cancelExpiredGames(ActivityLogger $activity, GameBroadcaster $broadcaster): int
    {
        $count = 0;

        Game::abandoned()->chunkById(50, function ($games) use (&$count, $activity, $broadcaster) {
            foreach ($games as $game) {
                $game->forceFill([
                    'status' => GameStatus::Cancelled,
                    'cancelled_at' => now(),
                    'current_player_id' => null,
                    'turn_deadline_at' => null,
                    'state_version' => $game->state_version + 1,
                ])->save();

                $activity->log(
                    game: $game,
                    type: GameActionType::GameCancelled,
                    message: 'The game was abandoned and has been closed.',
                );

                $broadcaster->gameCancelled($game);
                $count++;
            }
        });

        return $count;
    }

    /**
     * A browser that stops sending heartbeats is treated as disconnected, which is
     * what starts the reconnection grace period.
     */
    private function markSilentPlayers(ReconnectionService $reconnection): int
    {
        $timeout = (int) config('followsuit.presence_timeout_seconds', 60);
        $count = 0;

        GamePlayer::query()
            ->whereNull('left_at')
            ->where('connection_status', ConnectionStatus::Connected)
            ->where(fn ($query) => $query
                ->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<=', now()->subSeconds($timeout)))
            ->whereHas('game', fn ($query) => $query->whereIn('status', [GameStatus::Lobby, GameStatus::InProgress]))
            ->with('game')
            ->chunkById(100, function ($players) use (&$count, $reconnection) {
                foreach ($players as $player) {
                    if ($player->game === null) {
                        continue;
                    }

                    $reconnection->markDisconnected($player->game, $player);
                    $count++;
                }
            });

        return $count;
    }

    private function purgeOldGames(): int
    {
        $days = (int) config('followsuit.purge_completed_after_days', 7);

        return Game::whereIn('status', [GameStatus::Completed, GameStatus::Cancelled])
            ->where('updated_at', '<=', now()->subDays($days))
            ->delete();
    }
}
