<?php

use App\Models\GamePlayer;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Follow Suit broadcast channels
|--------------------------------------------------------------------------
|
| Both channels are private and are authorised with the `player` guard, which
| identifies the subscriber from the opaque token their browser holds. A player can
| only ever join the table channel of a game they are seated in, and only ever their
| own hand channel — the channel name must match their own public id.
|
*/

Broadcast::channel('game.{gamePublicId}', function (GamePlayer $player, string $gamePublicId): bool {
    return $player->game?->public_id === $gamePublicId && ! $player->hasLeft();
}, ['guards' => ['player']]);

Broadcast::channel('game.{gamePublicId}.player.{playerPublicId}', function (
    GamePlayer $player,
    string $gamePublicId,
    string $playerPublicId,
): bool {
    return $player->public_id === $playerPublicId
        && $player->game?->public_id === $gamePublicId
        && ! $player->hasLeft();
}, ['guards' => ['player']]);
