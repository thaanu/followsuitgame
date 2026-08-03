<?php

use App\Enums\CardLocation;
use App\Game\Services\GameStatePresenter;
use App\Game\Support\PlayerToken;
use App\Models\GameCard;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

it('sends a player only their own hand', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([
        ['7H', '8H'],
        ['2S', '3S'],
    ]);

    $response = get(route('games.table', $game), asPlayer($tokens[0]));

    $response->assertInertia(fn ($page) => $page
        ->component('Games/Table')
        ->has('hand.hand', 2)
        ->where('hand.player_id', $players[0]->public_id));

    // The opponent's cards must not appear anywhere in the rendered payload.
    expect($response->getContent())
        ->not->toContain('"code":"2S"')
        ->and($response->getContent())->not->toContain('"code":"3S"');
});

it('never includes the deck order or another hand in the public state', function () {
    ['game' => $game] = playing([
        ['7H', '8H'],
        ['2S', '3S'],
    ]);

    $state = app(GameStatePresenter::class)->publicState($game);

    expect(json_encode($state))
        ->not->toContain('original_deal_position')
        ->and($state['players'][0])->toHaveKey('hand_count')
        ->and($state['players'][0])->not->toHaveKey('hand');
});

it('refuses a game action from a browser with no token', function () {
    ['game' => $game] = playing([
        ['7H', '8H'],
        ['2H', '3H'],
    ]);

    post(route('games.resolve-turn', $game), [
        'state_version' => $game->state_version,
        'idempotency_key' => 'no-token-key',
    ])->assertSessionHas('gameError.reason', 'not_in_game');
});

it('refuses a game action from a token that belongs to another game', function () {
    ['game' => $game] = playing([['7H', '8H'], ['2H', '3H']]);
    ['tokens' => $otherTokens] = playing([['7C', '8C'], ['2C', '3C']]);

    post(route('games.play', $game), [
        'card_id' => 1,
        'state_version' => $game->state_version,
        'idempotency_key' => 'cross-game-key',
    ], asPlayer($otherTokens[0]))->assertSessionHas('gameError.reason', 'not_in_game');
});

it('refuses a card that belongs to another player', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([
        ['7H', '8H'],
        ['2H', '3H'],
    ]);

    $opponentCard = $players[1]->cards()->where('location', CardLocation::PlayerHand)->firstOrFail();

    post(route('games.play', $game), [
        'card_id' => $opponentCard->id,
        'state_version' => $game->state_version,
        'idempotency_key' => 'steal-key',
    ], asPlayer($tokens[0]))->assertSessionHas('gameError.reason', 'card_not_owned');
});

it('refuses a card that is not in the game at all', function () {
    ['game' => $game, 'tokens' => $tokens] = playing([['7H', '8H'], ['2H', '3H']]);

    post(route('games.play', $game), [
        'card_id' => 999999,
        'state_version' => $game->state_version,
        'idempotency_key' => 'ghost-card-key',
    ], asPlayer($tokens[0]))->assertSessionHas('gameError.reason', 'card_not_owned');
});

it('stores only a hash of the player token', function () {
    ['players' => $players, 'tokens' => $tokens] = makeLobby(players: 2);

    expect($players[0]->secure_token_hash)->toBe(PlayerToken::hash($tokens[0]))
        ->and($players[0]->secure_token_hash)->not->toBe($tokens[0]);
});

it('hides the token hash and invitation token from serialised models', function () {
    ['game' => $game, 'players' => $players] = makeLobby(players: 2);

    expect($players[0]->toArray())->not->toHaveKey('secure_token_hash')
        ->and($game->toArray())->not->toHaveKey('invitation_token');
});

it('hides the deal position on a card model', function () {
    ['game' => $game] = playing([['7H'], ['2H']]);

    expect(GameCard::where('game_id', $game->id)->first()->toArray())
        ->not->toHaveKey('original_deal_position');
});

it('rejects a malformed player token on the state endpoint', function () {
    ['game' => $game] = playing([['7H', '8H'], ['2H', '3H']]);

    getJson(route('games.state', $game), ['X-Player-Token' => 'short'])
        ->assertStatus(403)
        ->assertJson(['reason' => 'not_in_game']);
});

it('serves the private state to the player who owns the token', function () {
    ['game' => $game, 'players' => $players, 'tokens' => $tokens] = playing([['7H', '8H'], ['2H', '3H']]);

    getJson(route('games.state', $game), asPlayer($tokens[1]))
        ->assertOk()
        ->assertJsonPath('hand.player_id', $players[1]->public_id)
        ->assertJsonCount(2, 'hand.hand');
});
