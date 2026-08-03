<?php

use App\Enums\CardLocation;
use App\Enums\CardSuit;
use App\Enums\GameStatus;
use App\Game\Exceptions\GameException;
use App\Game\Services\GameplayService;
use App\Game\Services\RuleEngine;
use App\Models\Game;
use App\Models\GamePlayer;

function play(Game $game, GamePlayer $player, string $code, ?CardSuit $suit = null): void
{
    app(GameplayService::class)->playCard(
        game: $game->refresh(),
        player: $player->refresh(),
        cardId: (int) cardOf($player, $code)->id,
        chosenSuit: $suit,
        expectedStateVersion: (int) $game->refresh()->state_version,
        idempotencyKey: 'test-'.$code.'-'.$game->refresh()->state_version,
    );
}

function resolveTurn(Game $game, GamePlayer $player, string $key = 'stuck'): void
{
    app(GameplayService::class)->resolveStuckTurn(
        game: $game->refresh(),
        player: $player->refresh(),
        expectedStateVersion: (int) $game->refresh()->state_version,
        idempotencyKey: $key.'-'.$game->refresh()->state_version,
    );
}

describe('dealing', function () {
    it('deals every card evenly and keeps the remainder in the deck', function () {
        ['game' => $game, 'players' => $players] = makeLobby(players: 3);
        $game = startGame($game);

        expect($game->cards()->count())->toBe(52);

        foreach ($players as $player) {
            expect($player->handCount())->toBe(17);
        }

        expect($game->cards()->where('location', CardLocation::Deck)->count())->toBe(1);
    });

    it('deals a fixed number of cards and leaves the rest in the deck', function () {
        ['game' => $game, 'players' => $players] = makeLobby([
            'general' => ['deal_method' => 'fixed_count', 'fixed_cards_per_player' => 7],
        ], players: 3);

        $game = startGame($game);

        foreach ($players as $player) {
            expect($player->refresh()->handCount())->toBe(7);
        }

        expect($game->cards()->where('location', CardLocation::Deck)->count())->toBe(31);
    });

    it('includes jokers only when the deck type asks for them', function () {
        ['game' => $game] = makeLobby(['general' => ['deck_type' => 'standard_54_jokers']], players: 2);
        $game = startGame($game);

        expect($game->cards()->count())->toBe(54)
            ->and($game->cards()->where('rank', 'joker')->count())->toBe(2);
    });

    it('never exposes the deck order to the client payload', function () {
        ['game' => $game] = makeLobby(players: 2);
        $game = startGame($game);

        $card = $game->cards()->first();

        expect(array_keys($card->toClientArray()))
            ->not->toContain('original_deal_position')
            ->and($card->toArray())->not->toHaveKey('original_deal_position');
    });
});

describe('starting player', function () {
    it('starts with the host by default', function () {
        ['game' => $game, 'players' => $players] = makeLobby(players: 3);
        $game = startGame($game);

        expect($game->current_player_id)->toBe($players->first()->id);
    });

    it('starts with the holder of a specific card', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['2H', '3H'],
            ['AS', '4H'],
            ['5H', '6H'],
        ], ['general' => ['starting_player_method' => 'specific_card', 'starting_card_code' => 'AS']]);

        expect($game->current_player_id)->toBe($players[1]->id);
    });
});

describe('following suit', function () {
    it('lets the leader play anything and locks the suit for everybody else', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8S'],
            ['2H', '3S'],
            ['4H', '5S'],
        ]);

        $engine = app(RuleEngine::class);

        expect($engine->legalCards($game, $players[0])->pluck('card_code')->all())
            ->toEqualCanonicalizing(['7H', '8S']);

        play($game, $players[0], '7H');
        $game->refresh();

        expect($game->active_suit)->toBe(CardSuit::Hearts)
            ->and($engine->legalCards($game, $players[1]->refresh())->pluck('card_code')->all())
            ->toBe(['2H']);
    });

    it('rejects an off-suit card', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8S'],
            ['2H', '3S'],
            ['4H', '5S'],
        ]);

        play($game, $players[0], '7H');

        expect(fn () => play($game, $players[1], '3S'))
            ->toThrow(GameException::class, 'You must follow Hearts.');
    });

    it('rejects a move from a player whose turn it is not', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8S'],
            ['2H', '3S'],
            ['4H', '5S'],
        ]);

        expect(fn () => play($game, $players[1], '2H'))
            ->toThrow(GameException::class, 'It is not your turn.');
    });

    it('rejects a card the player does not hold', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8S'],
            ['2H', '3S'],
            ['4H', '5S'],
        ]);

        $otherCard = cardOf($players[1], '2H');

        expect(fn () => app(GameplayService::class)->playCard(
            $game, $players[0], (int) $otherCard->id, null, (int) $game->state_version, 'k1'
        ))->toThrow(GameException::class, 'That card is not in your hand.');
    });

    it('moves the turn on in seat order', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ]);

        play($game, $players[0], '7H');
        expect($game->refresh()->current_player_id)->toBe($players[1]->id);

        play($game, $players[1], '2H');
        expect($game->refresh()->current_player_id)->toBe($players[2]->id);

        play($game, $players[2], '4H');
        expect($game->refresh()->current_player_id)->toBe($players[0]->id);
    });
});

describe('unable to follow suit', function () {
    it('gives the whole pile to the player who cannot follow', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');

        expect(app(RuleEngine::class)->canFollowSuit($game->refresh(), $players[2]->refresh()))->toBeFalse();

        resolveTurn($game, $players[2]);

        $collector = $players[2]->refresh();

        expect($collector->cards_collected_count)->toBe(2)
            ->and($collector->piles_collected_count)->toBe(1)
            ->and($collector->failed_follow_suit_count)->toBe(1)
            ->and($collector->handCount())->toBe(4)
            ->and(handCodes($collector))->toContain('7H', '2H')
            ->and($game->refresh()->activePile())->toBeEmpty()
            ->and($game->active_suit)->toBeNull();
    });

    it('gives the lead to the collector by default', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        expect($game->refresh()->current_player_id)->toBe($players[2]->id);
    });

    it('gives the lead to the next player when configured that way', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ], ['follow_suit' => ['next_lead_rule' => 'next_in_order']]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        expect($game->refresh()->current_player_id)->toBe($players[0]->id);
    });

    it('moves collected cards to the penalty area when configured', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ], ['pile' => ['collected_cards_destination' => 'collected_area']]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        $collector = $players[2]->refresh();

        expect($collector->handCount())->toBe(2)
            ->and($collector->cards_collected_count)->toBe(2)
            ->and($game->cards()->where('location', CardLocation::CollectedCards)->count())->toBe(2);
    });

    it('counts collected cards without returning them to play', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ], ['pile' => ['collected_cards_destination' => 'count_only']]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        expect($players[2]->refresh()->handCount())->toBe(2)
            ->and($game->cards()->where('location', CardLocation::Discarded)->count())->toBe(2);
    });

    it('draws cards instead of collecting when configured', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ], [
            'general' => ['deal_method' => 'fixed_count', 'fixed_cards_per_player' => 2],
            'follow_suit' => ['unable_to_follow_action' => 'draw_cards', 'draw_card_count' => 2],
        ]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        expect($players[2]->refresh()->handCount())->toBe(4)
            ->and($game->refresh()->activePile())->toHaveCount(2);
    });

    it('skips the turn when configured', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ], ['follow_suit' => ['unable_to_follow_action' => 'skip_turn']]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        expect($game->refresh()->current_player_id)->toBe($players[0]->id)
            ->and($game->activePile())->toHaveCount(2);
    });

    it('refuses to let a player collect when they can follow suit', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ]);

        play($game, $players[0], '7H');

        expect(fn () => resolveTurn($game, $players[1]))
            ->toThrow(GameException::class, 'You can follow suit, so you must play a card.');
    });

    it('allows a voluntary collect when the rules permit it', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ], ['follow_suit' => ['allow_voluntary_collect' => true]]);

        play($game, $players[0], '7H');
        resolveTurn($game, $players[1]);

        expect($players[1]->refresh()->piles_collected_count)->toBe(1)
            ->and($players[1]->refresh()->failed_follow_suit_count)->toBe(0);
    });
});

describe('pile end conditions', function () {
    it('gives a completed pile to the highest card of the active suit', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['KH', '3H'],
            ['4H', '5H'],
        ], ['pile' => ['end_condition' => 'every_player_played']]);

        play($game, $players[0], '7H');
        play($game, $players[1], 'KH');
        play($game, $players[2], '4H');

        expect($players[1]->refresh()->piles_collected_count)->toBe(1)
            ->and($players[1]->refresh()->cards_collected_count)->toBe(3)
            ->and($game->refresh()->activePile())->toBeEmpty();
    });

    it('gives a completed pile to the lowest card when configured', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['KH', '3H'],
            ['4H', '5H'],
        ], [
            'pile' => ['end_condition' => 'every_player_played'],
            'ranking' => ['round_winner' => 'lowest_card'],
        ]);

        play($game, $players[0], '7H');
        play($game, $players[1], 'KH');
        play($game, $players[2], '4H');

        expect($players[2]->refresh()->piles_collected_count)->toBe(1);
    });

    it('ends the pile after a fixed number of cards', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['KH', '3H'],
            ['4H', '5H'],
        ], ['pile' => ['end_condition' => 'fixed_card_count', 'fixed_card_count' => 2]]);

        play($game, $players[0], '7H');
        play($game, $players[1], 'KH');

        expect($game->refresh()->activePile())->toBeEmpty()
            ->and($players[1]->refresh()->cards_collected_count)->toBe(2);
    });
});

describe('special cards', function () {
    $enable = fn (array $effects, array $extra = []) => array_replace_recursive([
        'special_cards' => ['enabled' => true, 'effects' => $effects],
    ], $extra);

    it('lets an ace change the active suit', function () use ($enable) {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['AS', '3H'],
            ['4C', '5C'],
        ], $enable(['ace_changes_suit' => true]));

        play($game, $players[0], '7H');
        play($game, $players[1], 'AS', CardSuit::Clubs);

        expect($game->refresh()->active_suit)->toBe(CardSuit::Clubs)
            ->and(app(RuleEngine::class)->legalCards($game, $players[2]->refresh())->pluck('card_code')->all())
            ->toEqualCanonicalizing(['4C', '5C']);
    });

    it('reverses the direction on a king', function () use ($enable) {
        ['game' => $game, 'players' => $players] = playing([
            ['KH', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ], $enable(['king_reverses_direction' => true]));

        play($game, $players[0], 'KH');

        expect($game->refresh()->direction->value)->toBe('counterclockwise')
            ->and($game->current_player_id)->toBe($players[2]->id);
    });

    it('skips the next player on a queen', function () use ($enable) {
        ['game' => $game, 'players' => $players] = playing([
            ['QH', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ], $enable(['queen_skips_next' => true]));

        play($game, $players[0], 'QH');

        expect($game->refresh()->current_player_id)->toBe($players[2]->id);
    });

    it('grants another turn on a jack', function () use ($enable) {
        ['game' => $game, 'players' => $players] = playing([
            ['JH', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ], $enable(['jack_extra_turn' => true]));

        play($game, $players[0], 'JH');

        expect($game->refresh()->current_player_id)->toBe($players[0]->id);
    });

    it('lets a joker stand in for any suit', function () use ($enable) {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['JOKER-1', '3S'],
            ['4C', '5C'],
        ], $enable(['joker_wild_suit' => true], ['general' => ['deck_type' => 'standard_54_jokers']]));

        play($game, $players[0], '7H');
        play($game, $players[1], 'JOKER-1', CardSuit::Clubs);

        expect($game->refresh()->active_suit)->toBe(CardSuit::Clubs);
    });

    it('clears the pile on the configured rank', function () use ($enable) {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ], $enable(['clear_pile' => true], ['special_cards' => ['clear_pile_rank' => 'two']]));

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');

        expect($game->refresh()->activePile())->toBeEmpty()
            ->and($game->cards()->where('location', CardLocation::Discarded)->count())->toBe(2)
            ->and($players[1]->refresh()->cards_collected_count)->toBe(0);
    });

    it('leaves special ranks inert when the effects are switched off', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['KH', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ]);

        play($game, $players[0], 'KH');

        expect($game->refresh()->direction->value)->toBe('clockwise')
            ->and($game->current_player_id)->toBe($players[1]->id);
    });
});

describe('winning', function () {
    it('ends the game when the first player empties their hand', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H'],
            ['2H'],
            ['4H'],
        ], ['general' => ['deal_method' => 'fixed_count', 'fixed_cards_per_player' => 1]]);

        play($game, $players[0], '7H');

        $game->refresh();

        expect($game->status)->toBe(GameStatus::Completed)
            ->and($players[0]->refresh()->finishing_position)->toBe(1)
            ->and($game->completed_at)->not->toBeNull();
    });

    it('refuses further moves once the game is complete', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H'],
            ['2H'],
            ['4H'],
        ], ['general' => ['deal_method' => 'fixed_count', 'fixed_cards_per_player' => 1]]);

        play($game, $players[0], '7H');

        expect(fn () => play($game, $players[1], '2H'))
            ->toThrow(GameException::class, 'This game is not accepting moves right now.');
    });

    it('ranks players by fewest collected cards when that is the win condition', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4S', '5S'],
        ], [
            'general' => ['deal_method' => 'fixed_count', 'fixed_cards_per_player' => 2],
            'pile' => ['collected_cards_destination' => 'count_only'],
            'winning' => ['condition' => 'fewest_collected_cards'],
        ]);

        play($game, $players[0], '7H');
        play($game, $players[1], '2H');
        resolveTurn($game, $players[2]);

        $standings = app(RuleEngine::class)->standings($game->refresh());

        expect($standings[count($standings) - 1]->player->id)->toBe($players[2]->id)
            ->and($standings[count($standings) - 1]->cardsCollected)->toBe(2);
    });
});

describe('concurrency and duplicate protection', function () {
    it('rejects a move built against a stale state version', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ]);

        $card = cardOf($players[0], '7H');

        expect(fn () => app(GameplayService::class)->playCard(
            $game, $players[0], (int) $card->id, null, (int) $game->state_version - 1, 'stale-key'
        ))->toThrow(GameException::class, 'The game has moved on since your last update.');
    });

    it('rejects a replay of the same idempotency key', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ]);

        $gameplay = app(GameplayService::class);

        $gameplay->playCard(
            $game, $players[0], (int) cardOf($players[0], '7H')->id, null, (int) $game->state_version, 'same-key'
        );

        $game->refresh();

        expect(fn () => $gameplay->playCard(
            $game, $players[1]->refresh(), (int) cardOf($players[1], '2H')->id, null, (int) $game->state_version, 'same-key'
        ))->toThrow(GameException::class, 'That action has already been processed.');
    });

    it('bumps the state version on every committed move', function () {
        ['game' => $game, 'players' => $players] = playing([
            ['7H', '8H'],
            ['2H', '3H'],
            ['4H', '5H'],
        ]);

        $before = (int) $game->state_version;

        play($game, $players[0], '7H');

        expect((int) $game->refresh()->state_version)->toBeGreaterThan($before);
    });
});
