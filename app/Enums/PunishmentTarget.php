<?php

namespace App\Enums;

enum PunishmentTarget: string
{
    case MostCardsCollected = 'most_cards_collected';
    case MostPilesCollected = 'most_piles_collected';
    case LastToEmptyHand = 'last_to_empty_hand';
    case LowestFinalPosition = 'lowest_final_position';
    case HostDecides = 'host_decides';

    public function label(): string
    {
        return match ($this) {
            self::MostCardsCollected => 'Most cards collected',
            self::MostPilesCollected => 'Most piles collected',
            self::LastToEmptyHand => 'Last player to empty their hand',
            self::LowestFinalPosition => 'Lowest final position',
            self::HostDecides => 'The host decides after the game',
        };
    }
}
