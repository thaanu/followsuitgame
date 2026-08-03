<?php

namespace App\Game\Services;

use App\Enums\CardLocation;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;

/**
 * The only class allowed to build payloads for the browser.
 *
 * Public payloads describe what everybody around the table can see. Private payloads
 * are addressed to exactly one player and are the only place card identities from a
 * hand appear.
 */
class GameStatePresenter
{
    public function __construct(
        private readonly GameplayService $gameplay,
        private readonly RuleEngine $rules,
        private readonly GameStartService $starter,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * State every player in the game may see.
     *
     * @return array<string, mixed>
     */
    public function publicState(Game $game): array
    {
        $game->loadMissing(['players']);
        $pile = $game->activePile();

        return [
            'id' => $game->public_id,
            'code' => $game->game_code,
            'name' => $game->name,
            'status' => $game->status->value,
            'status_label' => $game->status->label(),
            'state_version' => (int) $game->state_version,
            'round_number' => (int) $game->round_number,
            'turn_number' => (int) $game->turn_number,
            'direction' => $game->direction->value,
            'direction_label' => $game->direction->label(),
            'active_suit' => $game->active_suit?->value,
            'active_suit_label' => $game->active_suit?->label(),
            'active_suit_symbol' => $game->active_suit?->symbol(),
            'current_player_id' => $game->currentPlayer?->public_id,
            'host_player_id' => $game->host?->public_id,
            'maximum_players' => (int) $game->maximum_players,
            'is_paused' => (bool) $game->is_paused,
            'turn_deadline_at' => $game->turn_deadline_at?->toIso8601String(),
            'turn_timer_seconds' => $game->ruleConfiguration()->timerEnabled
                ? $game->ruleConfiguration()->timerSeconds
                : null,
            'server_time' => now()->toIso8601String(),
            'started_at' => $game->started_at?->toIso8601String(),
            'completed_at' => $game->completed_at?->toIso8601String(),
            'pile' => $pile->map(fn (GameCard $card) => [
                ...$card->toClientArray(),
                'played_by' => $card->player?->public_id,
            ])->values()->all(),
            'pile_count' => $pile->count(),
            'deck_count' => $game->cards()->where('location', CardLocation::Deck)->count(),
            'players' => $this->players($game),
            'activity' => $this->activity->recent($game),
        ];
    }

    /**
     * The private payload for one player: their hand and what they may do with it.
     *
     * @return array<string, mixed>
     */
    public function privateState(Game $game, GamePlayer $player): array
    {
        $hand = $player->hand();
        $legalIds = $this->gameplay->legalCardIds($game, $player);

        return [
            'player_id' => $player->public_id,
            'state_version' => (int) $game->state_version,
            'hand' => $hand->map(fn (GameCard $card) => [
                ...$card->toClientArray(),
                'is_legal' => in_array((int) $card->id, $legalIds, true),
            ])->values()->all(),
            'legal_card_ids' => $legalIds,
            'is_my_turn' => $game->current_player_id === $player->id,
            'must_collect' => $this->gameplay->isStuck($game, $player),
            'may_voluntarily_collect' => $game->current_player_id === $player->id
                && $this->rules->mayVoluntarilyCollect($game, $player),
            'requires_suit_choice_for' => $this->suitChoiceCardIds($game, $player, $legalIds),
        ];
    }

    /**
     * The lobby payload, which adds the invitation details and start requirements.
     *
     * @return array<string, mixed>
     */
    public function lobbyState(Game $game, bool $includeInvitation = false): array
    {
        $blockers = $this->starter->blockers($game);

        $state = [
            ...$this->publicState($game),
            'rule_preset' => $game->rule_preset->value,
            'rule_preset_label' => $game->rule_preset->label(),
            'rules_summary' => $game->ruleConfiguration()->summary(),
            'rules' => $game->ruleConfiguration()->toArray(),
            'punishment' => $game->punishmentConfiguration()?->summary(),
            'punishment_enabled' => (bool) $game->punishment_enabled,
            'can_start' => $blockers === [],
            'start_blockers' => $blockers,
        ];

        if ($includeInvitation) {
            $state['invitation_url'] = $game->invitationUrl();
        }

        return $state;
    }

    /**
     * Public, per-player information. Card identities are never included, only counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function players(Game $game): array
    {
        $handCounts = $game->cards()
            ->where('location', CardLocation::PlayerHand)
            ->selectRaw('player_id, COUNT(*) as total')
            ->groupBy('player_id')
            ->pluck('total', 'player_id');

        return $game->players()
            ->get()
            ->filter(fn (GamePlayer $player) => ! $player->hasLeft())
            ->map(fn (GamePlayer $player) => [
                'id' => $player->public_id,
                'nickname' => $player->nickname,
                'initials' => $player->initials(),
                'seat_number' => (int) $player->seat_number,
                'is_host' => (bool) $player->is_host,
                'is_ready' => (bool) $player->is_ready,
                'has_acknowledged_punishment' => $player->hasAcknowledgedPunishment(),
                'connection_status' => $player->connection_status->value,
                'is_connected' => $player->isConnected(),
                'hand_count' => (int) ($handCounts[$player->id] ?? 0),
                'cards_collected' => (int) $player->cards_collected_count,
                'piles_collected' => (int) $player->piles_collected_count,
                'failed_follow_suit' => (int) $player->failed_follow_suit_count,
                'finishing_position' => $player->finishing_position === null ? null : (int) $player->finishing_position,
                'is_current' => $game->current_player_id === $player->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Cards in this hand that would make the player nominate a new active suit.
     *
     * @param  array<int, int>  $legalIds
     * @return array<int, int>
     */
    private function suitChoiceCardIds(Game $game, GamePlayer $player, array $legalIds): array
    {
        if ($legalIds === []) {
            return [];
        }

        return $player->hand()
            ->filter(fn (GameCard $card) => in_array((int) $card->id, $legalIds, true)
                && $this->rules->requiresSuitChoice($game, $card))
            ->map(fn (GameCard $card) => (int) $card->id)
            ->values()
            ->all();
    }
}
