<?php

use App\Enums\ConnectionStatus;
use App\Enums\GameStatus;
use App\Events\PlayerDisconnected;
use App\Events\PlayerReconnected;
use App\Game\Services\GameBroadcaster;
use App\Game\Services\GameJoiningService;
use App\Game\Services\GameplayService;
use App\Game\Services\GameStatePresenter;
use App\Game\Services\ReconnectionService;
use App\Game\Services\RuleEngine;
use App\Jobs\ApplyDisconnectionPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

it('marks a player connected when they open the table', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H'], ['2H']]);
    $players[0]->forceFill(['connection_status' => ConnectionStatus::Disconnected])->save();

    get(route('games.table', $game), asPlayer($tokens[0]))->assertOk();

    expect($players[0]->refresh()->connection_status)->toBe(ConnectionStatus::Connected)
        ->and($players[0]->disconnected_at)->toBeNull();
});

it('records a heartbeat and reports the current state version', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H'], ['2H']]);

    postJson(route('games.heartbeat', $game), [], asPlayer($tokens[1]))
        ->assertOk()
        ->assertJsonPath('state_version', (int) $game->state_version)
        ->assertJsonPath('status', GameStatus::InProgress->value);

    expect($players[1]->refresh()->last_seen_at)->not->toBeNull();
});

it('restores the hand and the table for a reconnecting player', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([
        ['7H', '8H'],
        ['2H', '3H'],
    ]);

    $state = app(ReconnectionService::class)->resumeState(
        $game,
        $players[1],
        app(GameStatePresenter::class),
    );

    expect(array_column($state['private']['hand'], 'code'))->toBe(['2H', '3H'])
        ->and($state['public']['id'])->toBe($game->public_id)
        ->and(json_encode($state['public']))->not->toContain('"code":"7H"');
});

it('broadcasts a disconnection and schedules the policy job', function () {
    Event::fake([PlayerDisconnected::class]);
    Queue::fake();

    ['game' => $game, 'players' => $players] = playing([['7H'], ['2H']]);

    app(ReconnectionService::class)->markDisconnected($game, $players[1]);

    expect($players[1]->refresh()->connection_status)->toBe(ConnectionStatus::Disconnected)
        ->and($players[1]->disconnected_at)->not->toBeNull();

    Event::assertDispatched(PlayerDisconnected::class);
    Queue::assertPushed(ApplyDisconnectionPolicy::class);
});

it('broadcasts a reconnection when the player comes back', function () {
    Event::fake([PlayerReconnected::class]);

    ['game' => $game, 'players' => $players] = playing([['7H'], ['2H']]);
    $players[1]->forceFill([
        'connection_status' => ConnectionStatus::Disconnected,
        'disconnected_at' => now(),
    ])->save();

    app(ReconnectionService::class)->markConnected($game, $players[1]->refresh());

    Event::assertDispatched(PlayerReconnected::class);
});

it('pauses the game when the rules say so, and resumes on return', function () {
    ['game' => $game, 'players' => $players] = playing(
        [['7H'], ['2H']],
        ['connection' => ['disconnect_behaviour' => 'pause_game']],
    );

    $service = app(ReconnectionService::class);

    $service->markDisconnected($game, $players[1]);
    expect($game->refresh()->is_paused)->toBeTrue();

    $service->markConnected($game, $players[1]->refresh());
    expect($game->refresh()->is_paused)->toBeFalse();
});

it('refuses moves while the game is paused', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing(
        [['7H', '8H'], ['2H', '3H']],
        ['connection' => ['disconnect_behaviour' => 'pause_game']],
    );

    app(ReconnectionService::class)->markDisconnected($game, $players[1]);

    postJson(route('games.play', $game), [
        'card_id' => (int) cardOf($players[0], '7H')->id,
        'state_version' => (int) $game->refresh()->state_version,
        'idempotency_key' => 'paused-key',
    ], asPlayer($tokens[0]))->assertStatus(409)->assertJsonPath('reason', 'game_not_playable');
});

it('shows the reconnect screen with the seat it recognised', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H'], ['2H']]);

    get(route('games.reconnect', $game), asPlayer($tokens[0]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Reconnect')
            ->where('found', true)
            ->where('nickname', $players[0]->nickname));
});

it('shows the reconnect screen without a seat when the token is unknown', function () {
    ['game' => $game] = playing([['7H'], ['2H']]);

    get(route('games.reconnect', $game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Games/Reconnect')->where('found', false));
});

it('skips a disconnected player once their grace period ends', function () {
    ['game' => $game, 'players' => $players] = playing(
        [['7H', '8H'], ['2S', '3S']],
        ['connection' => ['disconnect_behaviour' => 'skip_after_timeout']],
    );

    // Player one leads, player two cannot follow and then drops out.
    app(GameplayService::class)->playCard(
        game: $game,
        player: $players[0],
        cardId: (int) cardOf($players[0], '7H')->id,
        expectedStateVersion: (int) $game->state_version,
        idempotencyKey: 'lead-key',
    );

    $game->refresh();
    $players[1]->forceFill([
        'connection_status' => ConnectionStatus::Disconnected,
        'disconnected_at' => now()->subMinutes(5),
    ])->save();

    (new ApplyDisconnectionPolicy($game->id, $players[1]->id))->handle(
        app(GameplayService::class),
        app(RuleEngine::class),
        app(GameJoiningService::class),
        app(GameBroadcaster::class),
    );

    // The stuck player took the pile, which is the configured unable-to-follow action.
    expect($players[1]->refresh()->piles_collected_count)->toBe(1);
});

it('removes a disconnected player from the lobby once the grace period ends', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 3);

    $players[2]->forceFill([
        'connection_status' => ConnectionStatus::Disconnected,
        'disconnected_at' => now()->subMinutes(5),
    ])->save();

    (new ApplyDisconnectionPolicy($game->id, $players[2]->id))->handle(
        app(GameplayService::class),
        app(RuleEngine::class),
        app(GameJoiningService::class),
        app(GameBroadcaster::class),
    );

    expect($players[2]->refresh()->left_at)->not->toBeNull();
});

it('does nothing when the player reconnected before the job ran', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 3);

    (new ApplyDisconnectionPolicy($game->id, $players[2]->id))->handle(
        app(GameplayService::class),
        app(RuleEngine::class),
        app(GameJoiningService::class),
        app(GameBroadcaster::class),
    );

    expect($players[2]->refresh()->left_at)->toBeNull();
});
