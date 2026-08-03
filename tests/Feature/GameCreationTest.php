<?php

use App\Enums\GameStatus;
use App\Enums\RulePreset;
use App\Game\Data\RuleConfiguration;
use App\Models\Game;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function creationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'nickname' => 'Ahmed',
        'name' => 'Friday night',
        'maximum_players' => 4,
        'rule_preset' => RulePreset::Classic->value,
        'rules' => RuleConfiguration::presetRules(RulePreset::Classic),
        'punishment_enabled' => false,
    ], $overrides);
}

it('shows the create game page', function () {
    get(route('games.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Games/Create')
            ->has('options.deck_types')
            ->has('presets'));
});

it('creates a game and seats the host', function () {
    post(route('games.store'), creationPayload())->assertRedirect();

    $game = Game::firstOrFail();

    expect($game->status)->toBe(GameStatus::Lobby)
        ->and($game->game_code)->toHaveLength(6)
        ->and($game->invitation_token)->toHaveLength(64)
        ->and($game->public_id)->not->toBeEmpty()
        ->and($game->expires_at)->not->toBeNull();

    $host = $game->host;

    expect($host)->not->toBeNull()
        ->and($host->nickname)->toBe('Ahmed')
        ->and($host->is_host)->toBeTrue()
        ->and($host->is_ready)->toBeTrue()
        ->and($host->seat_number)->toBe(1);
});

it('stores the host token as a hash, never in plain text', function () {
    post(route('games.store'), creationPayload())->assertRedirect();

    $host = Game::firstOrFail()->host;

    expect($host->secure_token_hash)->toHaveLength(64)
        ->and($host->secure_token_hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('rejects an invalid nickname', function () {
    post(route('games.store'), creationPayload(['nickname' => '<script>x</script>']))
        ->assertSessionHasErrors('nickname');
});

it('rejects a player count outside two to four', function () {
    post(route('games.store'), creationPayload(['maximum_players' => 5]))
        ->assertSessionHasErrors('maximum_players');
});

it('applies a named preset regardless of the posted rules', function () {
    post(route('games.store'), creationPayload([
        'rule_preset' => RulePreset::Quick->value,
        'rules' => ['winning' => ['condition' => 'first_to_empty_hand']],
    ]))->assertRedirect();

    expect(Game::firstOrFail()->ruleConfiguration()->winningCondition->value)
        ->toBe('fewest_collected_cards');
});

it('requires the punishment agreement checkbox when a punishment is set', function () {
    post(route('games.store'), creationPayload([
        'punishment_enabled' => true,
        'punishment' => [
            'title' => 'Make the tea',
            'description' => 'Loser makes tea for everyone.',
            'target' => 'most_cards_collected',
            'agreement_confirmed' => false,
        ],
    ]))->assertSessionHasErrors('punishment.agreement_confirmed');
});

it('saves a punishment and marks the host as having agreed', function () {
    post(route('games.store'), creationPayload([
        'punishment_enabled' => true,
        'punishment' => [
            'title' => 'Make the tea',
            'description' => 'Loser makes tea for everyone.',
            'emoji' => '🫖',
            'severity' => 'fun',
            'target' => 'most_piles_collected',
            'agreement_confirmed' => true,
        ],
    ]))->assertRedirect();

    $game = Game::firstOrFail();

    expect($game->punishment_enabled)->toBeTrue()
        ->and($game->punishmentConfiguration()->title)->toBe('Make the tea')
        ->and($game->punishmentConfiguration()->target->value)->toBe('most_piles_collected')
        ->and($game->host->punishment_acknowledged_at)->not->toBeNull();
});

it('strips markup from the punishment text', function () {
    post(route('games.store'), creationPayload([
        'punishment_enabled' => true,
        'punishment' => [
            'title' => '<b>Sing</b> a song',
            'description' => '<img src=x onerror=alert(1)>Any song',
            'target' => 'most_cards_collected',
            'agreement_confirmed' => true,
        ],
    ]))->assertRedirect();

    $punishment = Game::firstOrFail()->punishmentConfiguration();

    expect($punishment->title)->toBe('Sing a song')
        ->and($punishment->description)->toBe('Any song');
});

it('generates a unique game code per game', function () {
    post(route('games.store'), creationPayload())->assertRedirect();
    post(route('games.store'), creationPayload(['nickname' => 'Mahu']))->assertRedirect();

    expect(Game::pluck('game_code')->unique())->toHaveCount(2);
});
