<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\Gate;

function allows(GamePlayer $player, string $ability, Game $game): bool
{
    return Gate::forUser($player)->allows($ability, $game);
}

it('lets the host start, change settings, remove players and cancel', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 3);
    $host = $players[0];

    expect(allows($host, 'start', $game))->toBeTrue()
        ->and(allows($host, 'updateSettings', $game))->toBeTrue()
        ->and(allows($host, 'removePlayer', $game))->toBeTrue()
        ->and(allows($host, 'cancel', $game))->toBeTrue();
});

it('refuses host-only abilities to a guest', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 3);
    $guest = $players[1];

    expect(allows($guest, 'start', $game))->toBeFalse()
        ->and(allows($guest, 'updateSettings', $game))->toBeFalse()
        ->and(allows($guest, 'removePlayer', $game))->toBeFalse()
        ->and(allows($guest, 'cancel', $game))->toBeFalse()
        ->and(allows($guest, 'view', $game))->toBeTrue();
});

it('refuses every ability to a player from another game', function () {
    ['game' => $game] = makeLobby(players: 2);
    ['players' => $others] = makeLobby(players: 2);

    expect(allows($others[0], 'view', $game))->toBeFalse()
        ->and(allows($others[0], 'start', $game))->toBeFalse()
        ->and(allows($others[0], 'act', $game))->toBeFalse();
});

it('refuses every ability to a player who has left', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 3);
    $players[1]->forceFill(['left_at' => now()])->save();

    expect(allows($players[1]->refresh(), 'view', $game))->toBeFalse();
});

it('locks host settings once the game has started', function () {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']]);

    expect(allows($players[0], 'updateSettings', $game))->toBeFalse()
        ->and(allows($players[0], 'start', $game))->toBeFalse()
        ->and(allows($players[0], 'cancel', $game))->toBeTrue();
});

it('only allows a move from the player whose turn it is', function () {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']]);

    expect(allows($players[0], 'act', $game))->toBeTrue()
        ->and(allows($players[1], 'act', $game))->toBeFalse();
});

it('refuses a move while the game is paused', function () {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']]);
    $game->forceFill(['is_paused' => true])->save();

    expect(allows($players[0], 'act', $game->refresh()))->toBeFalse();
});

it('refuses a move once the game is over', function () {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']]);
    $game->forceFill(['status' => GameStatus::Completed])->save();

    expect(allows($players[0], 'act', $game->refresh()))->toBeFalse();
});

it('allows a rematch only to the host of a finished game', function () {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']]);

    expect(allows($players[0], 'rematch', $game))->toBeFalse();

    $game->forceFill(['status' => GameStatus::Completed])->save();

    expect(allows($players[0], 'rematch', $game->refresh()))->toBeTrue()
        ->and(allows($players[1], 'rematch', $game))->toBeFalse();
});
