<?php

use App\Events\CardPlayed;
use App\Events\PrivateStateUpdated;
use App\Game\Services\GameplayService;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

/**
 * Resolve a channel authorisation callback the way the broadcasting router does.
 */
function authorises(string $channel, GamePlayer $player, array $parameters): bool
{
    $channels = (fn () => $this->channels)->call(Broadcast::driver());

    foreach ($channels as $pattern => $callback) {
        if (str_replace(['{', '}'], '', $pattern) === str_replace(['{', '}'], '', $channel)) {
            return (bool) $callback($player, ...$parameters);
        }
    }

    return false;
}

it('lets a seated player join their game channel', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);

    expect(authorises('game.{gamePublicId}', $players[0], [$game->public_id]))->toBeTrue();
});

it('stops a player joining another game channel', function () {
    ['players' => $players] = makeLobby(players: 2);
    ['game' => $otherGame] = makeLobby(players: 2);

    expect(authorises('game.{gamePublicId}', $players[0], [$otherGame->public_id]))->toBeFalse();
});

it('stops a player who has left from rejoining the channel', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);
    $players[1]->forceFill(['left_at' => now()])->save();

    expect(authorises('game.{gamePublicId}', $players[1]->refresh(), [$game->public_id]))->toBeFalse();
});

it('lets a player join only their own hand channel', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);

    expect(authorises(
        'game.{gamePublicId}.player.{playerPublicId}',
        $players[0],
        [$game->public_id, $players[0]->public_id],
    ))->toBeTrue();
});

it('stops a player subscribing to an opponent hand channel', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);

    expect(authorises(
        'game.{gamePublicId}.player.{playerPublicId}',
        $players[0],
        [$game->public_id, $players[1]->public_id],
    ))->toBeFalse();
});

it('broadcasts a public event and one private event per player after a move', function () {
    Event::fake([CardPlayed::class, PrivateStateUpdated::class]);

    ['game' => $game, 'players' => $players] = playing([
        ['7H', '8H'],
        ['2H', '3H'],
        ['4H', '5H'],
    ]);

    app(GameplayService::class)->playCard(
        game: $game,
        player: $players[0],
        cardId: (int) cardOf($players[0], '7H')->id,
        expectedStateVersion: (int) $game->state_version,
        idempotencyKey: 'broadcast-key',
    );

    Event::assertDispatched(CardPlayed::class, fn (CardPlayed $event) => $event->gamePublicId === $game->public_id);
    Event::assertDispatchedTimes(PrivateStateUpdated::class, 3);
});

it('addresses each private event to one player and carries only that hand', function () {
    Event::fake([PrivateStateUpdated::class]);

    ['game' => $game, 'players' => $players] = playing([
        ['7H', '8H'],
        ['2H', '3H'],
    ]);

    app(GameplayService::class)->playCard(
        game: $game,
        player: $players[0],
        cardId: (int) cardOf($players[0], '7H')->id,
        expectedStateVersion: (int) $game->state_version,
        idempotencyKey: 'private-key',
    );

    Event::assertDispatched(
        PrivateStateUpdated::class,
        function (PrivateStateUpdated $event) use ($players) {
            if ($event->playerPublicId !== $players[1]->public_id) {
                return false;
            }

            $codes = array_column($event->state['hand'], 'code');

            return $codes === ['2H', '3H'];
        },
    );
});

it('names the public channel after the game and the private channel after the player', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);

    $public = new CardPlayed($game->public_id, [], []);
    $private = new PrivateStateUpdated($game->public_id, $players[0]->public_id, []);

    expect($public->broadcastOn()[0]->name)->toBe("private-game.{$game->public_id}")
        ->and($private->broadcastOn()[0]->name)
        ->toBe("private-game.{$game->public_id}.player.{$players[0]->public_id}");
});
