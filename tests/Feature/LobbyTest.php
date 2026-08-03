<?php

use App\Enums\GameStatus;
use App\Events\GameStarted;
use App\Events\PlayerReadyStatusChanged;
use App\Game\Services\GameStartService;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

it('shows the lobby to a seated player', function () {
    ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 2);

    get(route('games.lobby', $game), asPlayer($tokens[0]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Lobby')
            ->where('state.code', $game->game_code)
            ->has('state.invitation_url')
            ->has('state.players', 2));
});

it('sends a stranger to the join page instead of the lobby', function () {
    ['game' => $game] = makeLobby(players: 2);

    get(route('games.lobby', $game))
        ->assertRedirect(route('games.join.show', ['code' => $game->game_code]));
});

it('toggles a ready flag and broadcasts it', function () {
    Event::fake([PlayerReadyStatusChanged::class]);

    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 2);
    $players[1]->forceFill(['is_ready' => false])->save();

    post(route('games.ready', $game), ['ready' => true], asPlayer($tokens[1]))->assertRedirect();

    expect($players[1]->refresh()->is_ready)->toBeTrue();
    Event::assertDispatched(PlayerReadyStatusChanged::class);
});

it('lets the host remove another player', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 3);

    delete(route('games.players.remove', [$game, $players[2]]), [], asPlayer($tokens[0]))->assertRedirect();

    expect($players[2]->refresh()->left_at)->not->toBeNull()
        ->and($game->activePlayers()->count())->toBe(2);
});

it('stops a guest from removing anybody', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 3);

    delete(route('games.players.remove', [$game, $players[2]]), [], asPlayer($tokens[1]))
        ->assertSessionHas('gameError');

    expect($players[2]->refresh()->left_at)->toBeNull();
});

it('stops the host removing themselves', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 2);

    delete(route('games.players.remove', [$game, $players[0]]), [], asPlayer($tokens[0]))
        ->assertSessionHas('gameError');
});

it('lets a player leave the lobby', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 3);

    post(route('games.leave', $game), [], asPlayer($tokens[1]))->assertRedirect(route('home'));

    expect($players[1]->refresh()->left_at)->not->toBeNull();
});

describe('start requirements', function () {
    it('refuses to start with fewer than two players', function () {
        ['game' => $game] = makeLobby(players: 1);

        expect(app(GameStartService::class)->blockers($game))
            ->toContain('At least two players must join before the game can start.');
    });

    it('refuses to start while a guest is not ready', function () {
        ['game' => $game, 'players' => $players] = makeLobby(players: 3);
        $players[2]->forceFill(['is_ready' => false])->save();

        expect(app(GameStartService::class)->blockers($game->refresh()))
            ->toContain('Waiting for Player 3 to be ready.');
    });

    it('refuses to start until everyone has agreed to the punishment', function () {
        ['game' => $game, 'players' => $players] = makeLobby(players: 2, punishment: [
            'title' => 'Make the tea',
            'description' => '',
            'target' => 'most_cards_collected',
            'agreement_confirmed' => true,
        ]);

        $players[1]->forceFill(['punishment_acknowledged_at' => null])->save();

        expect(app(GameStartService::class)->blockers($game->refresh()))
            ->toContain('Waiting for Player 2 to agree to the punishment.');
    });

    it('lets a player acknowledge the punishment', function () {
        ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 2, punishment: [
            'title' => 'Make the tea',
            'description' => '',
            'target' => 'most_cards_collected',
            'agreement_confirmed' => true,
        ]);

        $players[1]->forceFill(['punishment_acknowledged_at' => null])->save();

        post(route('games.acknowledge-punishment', $game), [], asPlayer($tokens[1]))->assertRedirect();

        expect($players[1]->refresh()->punishment_acknowledged_at)->not->toBeNull();
    });

    it('starts the game, deals, and broadcasts', function () {
        Event::fake([GameStarted::class]);

        ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 3);

        post(route('games.start', $game), [], asPlayer($tokens[0]))
            ->assertRedirect(route('games.table', $game));

        $game->refresh();

        expect($game->status)->toBe(GameStatus::InProgress)
            ->and($game->started_at)->not->toBeNull()
            ->and($game->current_player_id)->not->toBeNull()
            ->and($game->round_number)->toBe(1)
            ->and($game->cards()->count())->toBe(52);

        Event::assertDispatched(GameStarted::class);
    });

    it('stops a guest from starting the game', function () {
        ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 2);

        post(route('games.start', $game), [], asPlayer($tokens[1]))->assertSessionHas('gameError');

        expect($game->refresh()->status)->toBe(GameStatus::Lobby);
    });
});

describe('settings', function () {
    it('lets the host change the rules before the game starts', function () {
        ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 2);

        $rules = $game->ruleConfiguration()->toArray();
        $rules['winning']['condition'] = 'fewest_collected_cards';

        put(route('games.settings', $game), [
            'name' => 'Renamed',
            'maximum_players' => 4,
            'rule_preset' => 'custom',
            'rules' => $rules,
            'punishment_enabled' => false,
        ], asPlayer($tokens[0]))->assertRedirect();

        expect($game->refresh()->name)->toBe('Renamed')
            ->and($game->ruleConfiguration()->winningCondition->value)->toBe('fewest_collected_cards');
    });

    it('stops a guest changing the rules', function () {
        ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 2);

        put(route('games.settings', $game), [
            'maximum_players' => 4,
            'rule_preset' => 'classic',
            'rules' => $game->ruleConfiguration()->toArray(),
            'punishment_enabled' => false,
        ], asPlayer($tokens[1]))->assertSessionHas('gameError');
    });

    it('refuses to lower the player limit below the seated players', function () {
        ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 3);

        put(route('games.settings', $game), [
            'maximum_players' => 2,
            'rule_preset' => 'classic',
            'rules' => $game->ruleConfiguration()->toArray(),
            'punishment_enabled' => false,
        ], asPlayer($tokens[0]))->assertSessionHas('gameError');
    });

    it('locks the settings once the game has started', function () {
        ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 2);
        startGame($game);

        put(route('games.settings', $game), [
            'maximum_players' => 4,
            'rule_preset' => 'classic',
            'rules' => $game->ruleConfiguration()->toArray(),
            'punishment_enabled' => false,
        ], asPlayer($tokens[0]))->assertSessionHas('gameError');
    });

    it('clears every punishment acknowledgement when the punishment changes', function () {
        ['game' => $game, 'players' => $players, 'tokens' => $tokens] = makeLobby(players: 2);

        put(route('games.settings', $game), [
            'maximum_players' => 2,
            'rule_preset' => 'custom',
            'rules' => $game->ruleConfiguration()->toArray(),
            'punishment_enabled' => true,
            'punishment' => [
                'title' => 'Sing a song',
                'description' => '',
                'target' => 'most_cards_collected',
                'agreement_confirmed' => true,
            ],
        ], asPlayer($tokens[0]))->assertRedirect();

        expect($players[1]->refresh()->punishment_acknowledged_at)->toBeNull()
            ->and($game->refresh()->host->punishment_acknowledged_at)->not->toBeNull();
    });
});

it('lets the host cancel the game', function () {
    ['game' => $game, 'tokens' => $tokens] = makeLobby(players: 2);

    post(route('games.cancel', $game), [], asPlayer($tokens[0]))->assertRedirect(route('home'));

    expect($game->refresh()->status)->toBe(GameStatus::Cancelled)
        ->and($game->cancelled_at)->not->toBeNull();
});
