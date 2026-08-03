<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Enums\GameActionType;
use App\Enums\GameStatus;
use App\Game\Exceptions\GameException;
use App\Game\Support\ActivityMessage;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use App\Models\GameRound;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Locks the lobby, shuffles, deals, and hands the first turn to the starting player.
 */
class GameStartService
{
    public function __construct(
        private readonly DeckService $deck,
        private readonly CardDealingService $dealer,
        private readonly RuleEngine $rules,
        private readonly TurnManager $turns,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Every reason the host cannot start yet, empty when the game is ready.
     *
     * @return array<int, string>
     */
    public function blockers(Game $game): array
    {
        $blockers = [];
        $players = $game->activePlayers()->get();

        if ($game->status !== GameStatus::Lobby) {
            $blockers[] = 'This game is no longer in the lobby.';

            return $blockers;
        }

        if ($players->count() < 2) {
            $blockers[] = 'At least two players must join before the game can start.';
        }

        if ($players->count() > $game->maximum_players) {
            $blockers[] = 'There are more players than the configured limit allows.';
        }

        $notReady = $players->filter(fn (GamePlayer $player) => ! $player->is_host && ! $player->is_ready);

        if ($notReady->isNotEmpty()) {
            $blockers[] = 'Waiting for '.$notReady->pluck('nickname')->join(', ', ' and ').' to be ready.';
        }

        if ($game->punishment_enabled) {
            $notAcknowledged = $players->filter(fn (GamePlayer $player) => ! $player->hasAcknowledgedPunishment());

            if ($notAcknowledged->isNotEmpty()) {
                $blockers[] = 'Waiting for '.$notAcknowledged->pluck('nickname')->join(', ', ' and ')
                    .' to agree to the punishment.';
            }
        }

        return $blockers;
    }

    public function canStart(Game $game): bool
    {
        return $this->blockers($game) === [];
    }

    /**
     * Start the game.
     *
     * @param  array<int, string>|null  $deckOrder  card codes forced to the top of the
     *                                              deck; used by deterministic tests
     */
    public function start(Game $game, ?array $deckOrder = null): Game
    {
        return DB::transaction(function () use ($game, $deckOrder) {
            /** @var Game $locked */
            $locked = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            $blockers = $this->blockers($locked);

            if ($blockers !== []) {
                throw GameException::cannotStart($blockers[0]);
            }

            $players = $locked->activePlayers()->get();

            $locked->forceFill([
                'status' => GameStatus::InProgress,
                'started_at' => now(),
                'round_number' => 1,
                'turn_number' => 0,
                'active_suit' => null,
                'pending_extra_turn' => false,
                'direction' => $locked->ruleConfiguration()->turnDirection,
            ])->save();

            $cardsEach = $this->dealRound($locked, $players, $deckOrder);

            $starter = $this->rules->startingPlayer($locked, $players);
            $this->turns->assignTurn($locked, $starter);

            $locked->forceFill([
                'starting_player_id' => $starter->id,
                'state_version' => $locked->state_version + 1,
            ])->save();

            GameRound::create([
                'game_id' => $locked->id,
                'round_number' => 1,
                'starting_player_id' => $starter->id,
                'started_at' => now(),
            ]);

            $this->activity->log(
                game: $locked,
                type: GameActionType::GameStarted,
                message: ActivityMessage::gameStarted($cardsEach),
                publicPayload: ['starting_player' => $starter->public_id],
            );

            return $locked->refresh();
        });
    }

    /**
     * Deal a fresh round: clears any cards from the previous round, shuffles, deals.
     *
     * @param  Collection<int, GamePlayer>  $players
     * @param  array<int, string>|null  $deckOrder
     * @return int the number of cards dealt to each player
     */
    public function dealRound(Game $game, Collection $players, ?array $deckOrder = null): int
    {
        $game->cards()->delete();

        $configuration = $game->ruleConfiguration();
        $deck = $this->deck->build($configuration);

        $deck = $deckOrder === null
            ? $this->deck->shuffle($deck)
            : $this->deck->orderBy($deck, $deckOrder);

        $this->dealer->deal($game, $deck, $players, $configuration);

        return $game->cards()
            ->where('location', CardLocation::PlayerHand)
            ->where('player_id', $players->first()?->id)
            ->count();
    }

    /**
     * Begin the next round of a multi-round game.
     */
    public function startNextRound(Game $game): void
    {
        $players = $game->activePlayers()->get();

        $game->forceFill([
            'round_number' => $game->round_number + 1,
            'active_suit' => null,
            'pending_extra_turn' => false,
        ])->save();

        $this->dealRound($game, $players);

        $starter = $this->rules->startingPlayer($game, $players);
        $this->turns->assignTurn($game, $starter);
        $game->forceFill(['starting_player_id' => $starter->id])->save();

        GameRound::updateOrCreate(
            ['game_id' => $game->id, 'round_number' => $game->round_number],
            ['starting_player_id' => $starter->id, 'started_at' => now()],
        );

        $this->activity->log(
            game: $game,
            type: GameActionType::RoundCompleted,
            message: ActivityMessage::roundCompleted($game->round_number - 1),
        );
    }

    /**
     * Draw cards from the remaining deck into a player's hand.
     *
     * @return int the number of cards actually drawn
     */
    public function drawCards(Game $game, GamePlayer $player, int $count): int
    {
        $cards = $game->cards()
            ->where('location', CardLocation::Deck)
            ->orderBy('original_deal_position')
            ->limit($count)
            ->get();

        if ($cards->isEmpty()) {
            return 0;
        }

        GameCard::whereIn('id', $cards->pluck('id')->all())->update([
            'location' => CardLocation::PlayerHand->value,
            'player_id' => $player->id,
            'updated_at' => now(),
        ]);

        return $cards->count();
    }
}
