<?php

namespace App\Game\Services;

use App\Enums\PunishmentTarget;
use App\Enums\TieBreakRule;
use App\Game\Data\Standing;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Decides who, if anybody, receives the host's punishment once the game ends.
 */
class PunishmentResolver
{
    /**
     * @param  array<int, Standing>  $standings
     * @return array{player: GamePlayer|null, reason: string, tied: bool, tied_players: array<int, string>, pending_host_decision: bool}|null
     */
    public function resolve(Game $game, array $standings): ?array
    {
        $punishment = $game->punishmentConfiguration();

        if ($punishment === null || $standings === []) {
            return null;
        }

        if ($punishment->target === PunishmentTarget::HostDecides) {
            return [
                'player' => null,
                'reason' => 'the host will decide',
                'tied' => false,
                'tied_players' => [],
                'pending_host_decision' => true,
            ];
        }

        $candidates = $this->candidates($standings, $punishment->target);

        if ($candidates === []) {
            return null;
        }

        $reason = $this->reason($punishment->target, $candidates[0]);

        if (count($candidates) === 1) {
            return [
                'player' => $candidates[0]->player,
                'reason' => $reason,
                'tied' => false,
                'tied_players' => [],
                'pending_host_decision' => false,
            ];
        }

        $resolved = $this->breakTie($game, $candidates);

        return [
            'player' => $resolved?->player,
            'reason' => $reason,
            'tied' => true,
            'tied_players' => array_map(fn (Standing $standing) => $standing->player->nickname, $candidates),
            'pending_host_decision' => $resolved === null,
        ];
    }

    /**
     * The worst-placed players under the chosen target, which may be several.
     *
     * @param  array<int, Standing>  $standings
     * @return array<int, Standing>
     */
    private function candidates(array $standings, PunishmentTarget $target): array
    {
        if ($standings === []) {
            return [];
        }

        $metric = fn (Standing $standing): int => match ($target) {
            PunishmentTarget::MostCardsCollected => $standing->cardsCollected,
            PunishmentTarget::MostPilesCollected => $standing->pilesCollected,
            PunishmentTarget::LastToEmptyHand,
            PunishmentTarget::LowestFinalPosition => $standing->position,
            PunishmentTarget::HostDecides => 0,
        };

        $worst = max(array_map($metric, $standings));

        return array_values(array_filter($standings, fn (Standing $standing) => $metric($standing) === $worst));
    }

    /**
     * @param  array<int, Standing>  $candidates
     */
    private function breakTie(Game $game, array $candidates): ?Standing
    {
        $rules = $game->ruleConfiguration();

        return match ($rules->tieBreakRule) {
            TieBreakRule::FewerCollectedCards => $this->worstBy($candidates, fn (Standing $s) => $s->cardsCollected),
            TieBreakRule::FewerCollectedPiles => $this->worstBy($candidates, fn (Standing $s) => $s->pilesCollected),
            // A shared result or a tie-breaker round leaves the decision open.
            TieBreakRule::Shared, TieBreakRule::TieBreakerRound => null,
        };
    }

    /**
     * @param  array<int, Standing>  $candidates
     * @param  callable(Standing): int  $metric
     */
    private function worstBy(array $candidates, callable $metric): ?Standing
    {
        usort($candidates, fn (Standing $a, Standing $b) => $metric($b) <=> $metric($a));

        if (count($candidates) > 1 && $metric($candidates[0]) === $metric($candidates[1])) {
            return null;
        }

        return $candidates[0] ?? null;
    }

    private function reason(PunishmentTarget $target, Standing $standing): string
    {
        return match ($target) {
            PunishmentTarget::MostCardsCollected => "collected the most cards ({$standing->cardsCollected})",
            PunishmentTarget::MostPilesCollected => "collected the most piles ({$standing->pilesCollected})",
            PunishmentTarget::LastToEmptyHand => 'was the last player left holding cards',
            PunishmentTarget::LowestFinalPosition => 'finished last',
            PunishmentTarget::HostDecides => 'was chosen by the host',
        };
    }
}
