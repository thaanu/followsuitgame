<?php

use App\Enums\GameStatus;
use App\Game\Support\PlayerToken;
use App\Models\Game;
use App\Models\GamePlayer;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function lobbyWithHost(array $attributes = []): Game
{
    $game = Game::factory()->create($attributes);

    $host = GamePlayer::factory()->host()->create([
        'game_id' => $game->id,
        'nickname' => 'Ahmed',
    ]);

    $game->forceFill(['host_player_id' => $host->id])->save();

    return $game->refresh();
}

it('shows the join page for a valid invitation', function () {
    $game = lobbyWithHost();

    get(route('games.join.show', ['code' => $game->game_code, 'token' => $game->invitation_token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Games/Join')->where('game.code', $game->game_code));
});

it('accepts the game code without the invitation token', function () {
    $game = lobbyWithHost();

    get(route('games.join.show', ['code' => $game->game_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Games/Join'));
});

it('rejects a wrong invitation token', function () {
    $game = lobbyWithHost();

    get(route('games.join.show', ['code' => $game->game_code, 'token' => 'wrong-token']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Games/Invalid')->where('reason', 'invalid_invitation'));
});

it('rejects an unknown game code', function () {
    get(route('games.join.show', ['code' => 'ZZZZZZ']))
        ->assertInertia(fn ($page) => $page->component('Games/Invalid')->where('reason', 'invalid_invitation'));
});

it('reports an expired invitation', function () {
    $game = lobbyWithHost(['expires_at' => now()->subHour()]);

    get(route('games.join.show', ['code' => $game->game_code]))
        ->assertInertia(fn ($page) => $page->component('Games/Invalid')->where('reason', 'expired_invitation'));
});

it('reports a game that has already started', function () {
    $game = lobbyWithHost(['status' => GameStatus::InProgress]);

    get(route('games.join.show', ['code' => $game->game_code]))
        ->assertInertia(fn ($page) => $page->component('Games/Invalid')->where('reason', 'already_started'));
});

it('reports a cancelled game', function () {
    $game = lobbyWithHost(['status' => GameStatus::Cancelled]);

    get(route('games.join.show', ['code' => $game->game_code]))
        ->assertInertia(fn ($page) => $page->component('Games/Invalid')->where('reason', 'game_cancelled'));
});

it('reports a full game', function () {
    $game = lobbyWithHost(['maximum_players' => 2]);
    GamePlayer::factory()->seat(2)->create(['game_id' => $game->id]);

    get(route('games.join.show', ['code' => $game->game_code]))
        ->assertInertia(fn ($page) => $page->component('Games/Invalid')->where('reason', 'game_full'));
});

it('seats a new player and gives them the next seat', function () {
    $game = lobbyWithHost();

    post(route('games.join.store', $game->game_code), ['nickname' => 'Mahu'])
        ->assertRedirect(route('games.lobby', $game));

    $player = $game->players()->where('nickname', 'Mahu')->firstOrFail();

    expect($player->seat_number)->toBe(2)
        ->and($player->is_host)->toBeFalse()
        ->and($player->is_ready)->toBeFalse();
});

it('refuses a duplicate nickname, ignoring case', function () {
    $game = lobbyWithHost();

    post(route('games.join.store', $game->game_code), ['nickname' => 'ahmed'])
        ->assertSessionHas('gameError');

    expect($game->players()->count())->toBe(1);
});

it('refuses to seat more players than the limit allows', function () {
    $game = lobbyWithHost(['maximum_players' => 2]);
    GamePlayer::factory()->seat(2)->create(['game_id' => $game->id, 'nickname' => 'Mahu']);

    post(route('games.join.store', $game->game_code), ['nickname' => 'Shan'])
        ->assertSessionHas('gameError');

    expect($game->activePlayers()->count())->toBe(2);
});

it('returns the same seat to a browser that already holds a token', function () {
    $game = lobbyWithHost();
    $token = PlayerToken::generate();

    $existing = GamePlayer::factory()->seat(2)->withToken($token)->create([
        'game_id' => $game->id,
        'nickname' => 'Mahu',
    ]);

    post(route('games.join.store', $game->game_code), [
        'nickname' => 'Somebody Else',
        'player_token' => $token,
    ])->assertRedirect(route('games.lobby', $game));

    expect($game->activePlayers()->count())->toBe(2)
        ->and($existing->refresh()->nickname)->toBe('Mahu');
});

it('lets a player who already joined return to the lobby instead of joining twice', function () {
    $game = lobbyWithHost();
    $token = PlayerToken::generate();

    GamePlayer::factory()->seat(2)->withToken($token)->create(['game_id' => $game->id, 'nickname' => 'Mahu']);

    get(route('games.join.show', ['code' => $game->game_code]), ['X-Player-Token' => $token])
        ->assertRedirect(route('games.lobby', $game));
});

it('sends a returning player to the table when the game is running', function () {
    $game = lobbyWithHost(['status' => GameStatus::InProgress]);
    $token = PlayerToken::generate();

    GamePlayer::factory()->seat(2)->withToken($token)->create(['game_id' => $game->id, 'nickname' => 'Mahu']);

    get(route('games.join.show', ['code' => $game->game_code]), ['X-Player-Token' => $token])
        ->assertRedirect(route('games.table', $game));
});

it('rejects a nickname that is too short', function () {
    $game = lobbyWithHost();

    post(route('games.join.store', $game->game_code), ['nickname' => 'M'])
        ->assertSessionHasErrors('nickname');
});
