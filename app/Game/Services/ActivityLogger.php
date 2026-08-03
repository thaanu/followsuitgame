<?php

namespace App\Game\Services;

use App\Enums\GameActionType;
use App\Models\Game;
use App\Models\GameAction;
use App\Models\GameCard;
use App\Models\GamePlayer;

/**
 * Appends to the per-game activity log. Public payloads reach every player, so they
 * must never contain hidden card information; private payloads stay on the server.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $publicPayload
     * @param  array<string, mixed>|null  $privatePayload
     */
    public function log(
        Game $game,
        GameActionType $type,
        ?GamePlayer $player = null,
        ?string $message = null,
        array $publicPayload = [],
        ?array $privatePayload = null,
        ?GameCard $card = null,
        ?string $idempotencyKey = null,
    ): GameAction {
        return GameAction::create([
            'game_id' => $game->id,
            'player_id' => $player?->id,
            'action_type' => $type,
            'card_id' => $card?->id,
            // Both default at the database level, so a freshly created game has them
            // unset in memory until it is refreshed.
            'turn_number' => (int) ($game->turn_number ?? 0),
            'state_version' => (int) ($game->state_version ?? 0),
            'idempotency_key' => $idempotencyKey,
            'public_payload' => [...$publicPayload, 'message' => $message ?? ''],
            'private_payload' => $privatePayload,
            'created_at' => now(),
        ]);
    }

    /**
     * The most recent activity entries, oldest first.
     *
     * @return array<int, array{id: int, type: string, message: string, at: string|null}>
     */
    public function recent(Game $game, int $limit = 30): array
    {
        return $game->actions()
            ->whereNotNull('public_payload')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (GameAction $action) => $action->toActivityArray())
            ->filter(fn (array $entry) => $entry['message'] !== '')
            ->values()
            ->all();
    }
}
