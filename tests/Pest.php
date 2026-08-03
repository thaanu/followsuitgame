<?php

use App\Enums\RulePreset;
use App\Game\Data\RuleConfiguration;
use App\Game\Services\GameStartService;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Game builders
|--------------------------------------------------------------------------
|
| Tests never rely on the shuffle. `startGame()` takes an explicit deck order and
| `handsToDeckOrder()` turns "who should hold what" into that order, so every
| scenario is fully deterministic.
|
*/

/**
 * Create a lobby with a host and the requested number of guests, all ready.
 *
 * @param  array<string, mixed>  $rules  partial rules merged over the Classic preset
 * @param  array<int, string>  $nicknames
 * @param  array<string, mixed>|null  $punishment
 * @return array{game: Game, players: Collection<int, GamePlayer>, tokens: array<int, string>}
 */
function makeLobby(array $rules = [], int $players = 3, array $nicknames = [], ?array $punishment = null): array
{
    $game = Game::factory()->create([
        'rules' => array_replace_recursive(RuleConfiguration::presetRules(RulePreset::Classic), $rules),
        'rule_preset' => RulePreset::Custom,
        'maximum_players' => max(2, min(4, $players)),
        'punishment_enabled' => $punishment !== null,
        'punishment' => $punishment,
    ]);

    $tokens = [];
    $created = collect();

    for ($seat = 1; $seat <= $players; $seat++) {
        $token = Str::random(64);
        $tokens[] = $token;

        $created->push(GamePlayer::factory()->create([
            'game_id' => $game->id,
            'nickname' => $nicknames[$seat - 1] ?? "Player {$seat}",
            'seat_number' => $seat,
            'is_host' => $seat === 1,
            'is_ready' => true,
            'secure_token_hash' => hash('sha256', $token),
            'punishment_acknowledged_at' => $punishment !== null ? now() : null,
        ]));
    }

    $game->forceFill(['host_player_id' => $created->first()->id])->save();

    return ['game' => $game->refresh(), 'players' => $created, 'tokens' => $tokens];
}

/**
 * Build a deck order that deals the given hands, in seat order.
 *
 * The dealer deals round-robin, so card `n` of each player is interleaved. Any card
 * not named here follows in the deck, which is where draws come from.
 *
 * @param  array<int, array<int, string>>  $hands  card codes per seat
 * @return array<int, string>
 */
function handsToDeckOrder(array $hands): array
{
    $order = [];
    $rounds = $hands === [] ? 0 : max(array_map('count', $hands));

    for ($round = 0; $round < $rounds; $round++) {
        foreach ($hands as $hand) {
            if (isset($hand[$round])) {
                $order[] = $hand[$round];
            }
        }
    }

    return $order;
}

/**
 * Start a game with an exact deal.
 *
 * @param  array<int, array<int, string>>  $hands  card codes per seat
 */
function startGame(Game $game, array $hands = []): Game
{
    return app(GameStartService::class)->start($game, $hands === [] ? null : handsToDeckOrder($hands));
}

/**
 * Create a lobby and start it in one step.
 *
 * @param  array<int, array<int, string>>  $hands
 * @param  array<string, mixed>  $rules
 * @param  array<string, mixed>|null  $punishment
 * @return array{game: Game, players: Collection<int, GamePlayer>, tokens: array<int, string>}
 */
function playing(array $hands, array $rules = [], ?array $punishment = null): array
{
    // Unless the scenario says otherwise, deal exactly the hands that were asked for
    // so the rest of the deck stays out of the way.
    if (! isset($rules['general']['deal_method'])) {
        $rules = array_replace_recursive($rules, [
            'general' => [
                'deal_method' => 'fixed_count',
                'fixed_cards_per_player' => $hands === [] ? 1 : count($hands[0]),
            ],
        ]);
    }

    $lobby = makeLobby($rules, count($hands), punishment: $punishment);
    $lobby['game'] = startGame($lobby['game'], $hands);
    $lobby['players'] = $lobby['players']->map(fn (GamePlayer $player) => $player->refresh());

    return $lobby;
}

/**
 * The card in a player's hand with the given code.
 */
function cardOf(GamePlayer $player, string $code): GameCard
{
    return $player->cards()->where('card_code', $code)->firstOrFail();
}

/**
 * The card codes a player is holding.
 *
 * @return array<int, string>
 */
function handCodes(GamePlayer $player): array
{
    return $player->hand()->pluck('card_code')->all();
}

/**
 * Headers that authenticate a request as the given player.
 *
 * @return array<string, string>
 */
function asPlayer(string $token): array
{
    return ['X-Player-Token' => $token];
}
