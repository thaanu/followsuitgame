<?php

use App\Game\Services\ActivityLogger;
use App\Game\Services\GameplayService;
use App\Game\Services\RuleEngine;
use App\Jobs\EnforceTurnTimeout;
use App\Models\Game;
use Illuminate\Support\Facades\Queue;

function runTimeout(Game $game, int $stateVersion): void
{
    (new EnforceTurnTimeout($game->id, $stateVersion))->handle(
        app(GameplayService::class),
        app(RuleEngine::class),
        app(ActivityLogger::class),
    );
}

$timerRules = ['timer' => ['enabled' => true, 'seconds' => 15, 'timeout_action' => 'play_random_legal']];

it('sets a server-side deadline when the timer is on', function () use ($timerRules) {
    ['game' => $game] = playing([['7H', '8H'], ['2H', '3H']], $timerRules);

    expect($game->turn_deadline_at)->not->toBeNull()
        ->and($game->turn_deadline_at->diffInSeconds(now()))->toBeLessThanOrEqual(16);
});

it('leaves the deadline unset when the timer is off', function () {
    ['game' => $game] = playing([['7H', '8H'], ['2H', '3H']]);

    expect($game->turn_deadline_at)->toBeNull();
});

it('schedules the timeout job when the timer is on', function () use ($timerRules) {
    Queue::fake();

    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']], $timerRules);

    app(GameplayService::class)->playCard(
        game: $game,
        player: $players[0],
        cardId: (int) cardOf($players[0], '7H')->id,
        expectedStateVersion: (int) $game->state_version,
        idempotencyKey: 'timer-key',
    );

    Queue::assertPushed(EnforceTurnTimeout::class);
});

it('plays a legal card for a player who runs out of time', function () use ($timerRules) {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']], $timerRules);

    $game->forceFill(['turn_deadline_at' => now()->subSecond()])->save();

    runTimeout($game, (int) $game->state_version);

    expect($game->refresh()->activePile())->toHaveCount(1)
        ->and($players[0]->refresh()->handCount())->toBe(1)
        ->and($players[0]->turn_timeout_count)->toBe(1);
});

it('takes the pile for a player who times out under the collect rule', function () {
    ['game' => $game, 'players' => $players] = playing(
        [['7H', '8H'], ['2S', '3S']],
        ['timer' => ['enabled' => true, 'seconds' => 15, 'timeout_action' => 'collect_pile']],
    );

    app(GameplayService::class)->playCard(
        game: $game,
        player: $players[0],
        cardId: (int) cardOf($players[0], '7H')->id,
        expectedStateVersion: (int) $game->state_version,
        idempotencyKey: 'lead',
    );

    $game->refresh()->forceFill(['turn_deadline_at' => now()->subSecond()])->save();

    runTimeout($game, (int) $game->state_version);

    expect($players[1]->refresh()->piles_collected_count)->toBe(1);
});

it('does nothing when the player moved before the timer fired', function () use ($timerRules) {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']], $timerRules);

    $staleVersion = (int) $game->state_version;

    app(GameplayService::class)->playCard(
        game: $game,
        player: $players[0],
        cardId: (int) cardOf($players[0], '7H')->id,
        expectedStateVersion: $staleVersion,
        idempotencyKey: 'moved-in-time',
    );

    $pileBefore = $game->refresh()->activePile()->count();

    runTimeout($game, $staleVersion);

    expect($game->refresh()->activePile())->toHaveCount($pileBefore)
        ->and($players[1]->refresh()->turn_timeout_count)->toBe(0);
});

it('does nothing when the deadline has not passed yet', function () use ($timerRules) {
    ['game' => $game, 'players' => $players] = playing([['7H', '8H'], ['2H', '3H']], $timerRules);

    runTimeout($game, (int) $game->state_version);

    expect($game->refresh()->activePile())->toBeEmpty()
        ->and($players[0]->refresh()->turn_timeout_count)->toBe(0);
});

it('leaves the turn alone when the host decides on timeouts', function () {
    ['game' => $game, 'players' => $players] = playing(
        [['7H', '8H'], ['2H', '3H']],
        ['timer' => ['enabled' => true, 'seconds' => 15, 'timeout_action' => 'host_decides']],
    );

    $game->forceFill(['turn_deadline_at' => now()->subSecond()])->save();

    runTimeout($game, (int) $game->state_version);

    expect($game->refresh()->current_player_id)->toBe($players[0]->id)
        ->and($game->activePile())->toBeEmpty();
});
