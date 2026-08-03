<?php

use App\Enums\ConnectionStatus;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GameAction;
use App\Models\GameCard;
use App\Models\GamePlayer;

use function Pest\Laravel\artisan;

it('cancels a game that has passed its expiry', function () {
    ['game' => $game] = makeLobby(players: 2);
    $game->forceFill(['expires_at' => now()->subHour()])->save();

    artisan('games:cleanup')->assertSuccessful();

    expect($game->refresh()->status)->toBe(GameStatus::Cancelled)
        ->and($game->cancelled_at)->not->toBeNull();
});

it('leaves a game that has not expired alone', function () {
    ['game' => $game] = makeLobby(players: 2);

    artisan('games:cleanup')->assertSuccessful();

    expect($game->refresh()->status)->toBe(GameStatus::Lobby);
});

it('marks a silent player as disconnected', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);
    $players[1]->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();

    artisan('games:cleanup')->assertSuccessful();

    expect($players[1]->refresh()->connection_status)->toBe(ConnectionStatus::Disconnected);
});

it('leaves a player who pinged recently connected', function () {
    ['players' => $players] = makeLobby(players: 2);

    artisan('games:cleanup')->assertSuccessful();

    expect($players[1]->refresh()->connection_status)->toBe(ConnectionStatus::Connected);
});

it('purges finished games past the retention window only when asked', function () {
    $old = Game::factory()->completed()->create();
    $old->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();

    artisan('games:cleanup')->assertSuccessful();
    expect(Game::whereKey($old->id)->exists())->toBeTrue();

    artisan('games:cleanup --purge')->assertSuccessful();
    expect(Game::whereKey($old->id)->exists())->toBeFalse();
});

it('cascades cards, players and actions when a game is deleted', function () {
    ['game' => $game] = playing([['7H', '8H'], ['2H', '3H']]);

    $game->delete();

    expect(GamePlayer::where('game_id', $game->id)->count())->toBe(0)
        ->and(GameCard::where('game_id', $game->id)->count())->toBe(0)
        ->and(GameAction::where('game_id', $game->id)->count())->toBe(0);
});
