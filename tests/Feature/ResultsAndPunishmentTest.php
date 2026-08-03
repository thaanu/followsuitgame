<?php

use App\Enums\GameStatus;
use App\Game\Services\GameplayService;
use App\Game\Services\PunishmentResolver;
use App\Game\Services\RuleEngine;
use App\Game\Services\WinningConditionService;
use App\Models\Game;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function punishment(string $target, array $overrides = []): array
{
    return array_replace([
        'title' => 'Make the tea',
        'description' => 'Loser makes tea for everyone.',
        'emoji' => '🫖',
        'severity' => 'fun',
        'target' => $target,
        'agreement_confirmed' => true,
    ], $overrides);
}

/**
 * Play far enough that player two has been forced to collect exactly one pile.
 */
function gameWithACollectedPile(array $rules = [], ?array $punishment = null): array
{
    $context = playing(
        [['7H', '8H'], ['2S', '3C']],
        $rules,
        $punishment,
    );

    ['game' => $game, 'players' => $players] = $context;
    $gameplay = app(GameplayService::class);

    // Player two cannot follow Hearts, so they take the pile.
    $gameplay->playCard($game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'k1');
    $gameplay->resolveStuckTurn($game->refresh(), $players[1]->refresh(), (int) $game->refresh()->state_version, 'k2');

    $game->refresh();
    $players = $players->map(fn ($player) => $player->refresh());

    return ['game' => $game, 'players' => $players];
}

it('completes the game and stores the standings', function () {
    ['game' => $game, 'players' => $players] = playing([['7H'], ['2H']]);

    app(GameplayService::class)->playCard(
        $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'win-key',
    );

    $game->refresh();
    $results = app(WinningConditionService::class)->results($game);

    expect($game->status)->toBe(GameStatus::Completed)
        ->and($results['winner']['nickname'])->toBe($players[0]->nickname)
        ->and($results['standings'])->toHaveCount(2)
        ->and($results['turns_played'])->toBeGreaterThan(0)
        ->and($game->rounds()->whereNotNull('result')->exists())->toBeTrue();
});

it('shows the results page with every statistic the spec asks for', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H'], ['2H']]);

    app(GameplayService::class)->playCard(
        $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'stats-key',
    );

    get(route('games.results', $game->refresh()), asPlayer($tokens[0]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Results')
            ->has('results.standings.0.cards_remaining')
            ->has('results.standings.0.cards_collected')
            ->has('results.standings.0.piles_collected')
            ->has('results.standings.0.failed_follow_suit')
            ->has('results.turns_played')
            ->has('results.duration_seconds')
            ->has('rulesSummary'));
});

it('sends players on a running game away from the results page', function () {
    ['game' => $game, 'tokens' => $tokens] = playing([['7H', '8H'], ['2H', '3H']]);

    get(route('games.results', $game), asPlayer($tokens[0]))->assertRedirect(route('games.table', $game));
});

describe('punishment resolution', function () {
    it('punishes whoever collected the most cards', function () {
        ['game' => $game, 'players' => $players] = gameWithACollectedPile(punishment: punishment('most_cards_collected'));

        $standings = app(RuleEngine::class)->standings($game->refresh());
        $resolved = app(PunishmentResolver::class)->resolve($game, $standings);

        expect($resolved['player']->id)->toBe($players[1]->id)
            ->and($resolved['reason'])->toContain('collected the most cards')
            ->and($resolved['tied'])->toBeFalse();
    });

    it('punishes whoever collected the most piles', function () {
        ['game' => $game, 'players' => $players] = gameWithACollectedPile(punishment: punishment('most_piles_collected'));

        $standings = app(RuleEngine::class)->standings($game->refresh());
        $resolved = app(PunishmentResolver::class)->resolve($game, $standings);

        expect($resolved['player']->id)->toBe($players[1]->id)
            ->and($resolved['reason'])->toContain('collected the most piles');
    });

    it('leaves the choice open when the host decides', function () {
        ['game' => $game] = gameWithACollectedPile(punishment: punishment('host_decides'));

        $standings = app(RuleEngine::class)->standings($game->refresh());
        $resolved = app(PunishmentResolver::class)->resolve($game, $standings);

        expect($resolved['player'])->toBeNull()
            ->and($resolved['pending_host_decision'])->toBeTrue();
    });

    it('reports a tie and leaves it unresolved under a shared result', function () {
        ['game' => $game] = playing(
            [['7H', '8H'], ['2H', '3H']],
            ['winning' => ['tie_break' => 'shared']],
            punishment('most_cards_collected'),
        );

        // Neither player has collected anything, so both are equally "worst".
        $standings = app(RuleEngine::class)->standings($game);
        $resolved = app(PunishmentResolver::class)->resolve($game, $standings);

        expect($resolved['tied'])->toBeTrue()
            ->and($resolved['player'])->toBeNull()
            ->and($resolved['tied_players'])->toHaveCount(2);
    });

    it('returns nothing when no punishment is configured', function () {
        ['game' => $game] = gameWithACollectedPile();

        $standings = app(RuleEngine::class)->standings($game);

        expect(app(PunishmentResolver::class)->resolve($game, $standings))->toBeNull();
    });

    it('includes the punishment in the stored results', function () {
        ['game' => $game, 'players' => $players] = playing(
            [['7H'], ['2S']],
            [],
            punishment('most_cards_collected'),
        );

        app(GameplayService::class)->playCard(
            $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'p-key',
        );

        $results = app(WinningConditionService::class)->results($game->refresh());

        expect($results['punishment'])->not->toBeNull()
            ->and($results['punishment']['title'])->toBe('Make the tea')
            ->and($results['punishment']['emoji'])->toBe('🫖');
    });
});

describe('rematch', function () {
    it('lets the host create a rematch with the same rules', function () {
        ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing(
            [['7H'], ['2H']],
            ['winning' => ['condition' => 'fewest_collected_cards']],
        );

        app(GameplayService::class)->playCard(
            $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'r-key',
        );

        post(route('games.rematch', $game->refresh()), [], asPlayer($tokens[0]))->assertRedirect();

        $rematch = Game::whereKeyNot($game->id)->firstOrFail();

        expect($game->refresh()->rematch_game_id)->toBe($rematch->id)
            ->and($rematch->status)->toBe(GameStatus::Lobby)
            ->and($rematch->ruleConfiguration()->winningCondition->value)->toBe('fewest_collected_cards')
            ->and($rematch->host->nickname)->toBe($players[0]->nickname);
    });

    it('stops a guest starting a rematch', function () {
        ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H'], ['2H']]);

        app(GameplayService::class)->playCard(
            $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'rg-key',
        );

        post(route('games.rematch', $game->refresh()), [], asPlayer($tokens[1]))->assertSessionHas('gameError');

        expect(Game::count())->toBe(1);
    });

    it('refuses a rematch before the game has finished', function () {
        ['game' => $game, 'tokens' => $tokens] = playing([['7H', '8H'], ['2H', '3H']]);

        post(route('games.rematch', $game), [], asPlayer($tokens[0]))->assertSessionHas('gameError');
    });

    it('reuses the rematch that already exists', function () {
        ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H'], ['2H']]);

        app(GameplayService::class)->playCard(
            $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 're-key',
        );

        post(route('games.rematch', $game->refresh()), [], asPlayer($tokens[0]))->assertRedirect();
        post(route('games.rematch', $game->refresh()), [], asPlayer($tokens[0]))->assertRedirect();

        expect(Game::count())->toBe(2);
    });
});
