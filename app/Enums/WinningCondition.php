<?php

namespace App\Enums;

enum WinningCondition: string
{
    case FirstToEmptyHand = 'first_to_empty_hand';
    case LastRemainingLoses = 'last_remaining_loses';
    case FewestCollectedCards = 'fewest_collected_cards';
    case FewestCollectedPiles = 'fewest_collected_piles';
    case PlayUntilOneHasCards = 'play_until_one_has_cards';
    case FixedRounds = 'fixed_rounds';

    public function label(): string
    {
        return match ($this) {
            self::FirstToEmptyHand => 'First player to empty their hand wins',
            self::LastRemainingLoses => 'Last remaining player loses',
            self::FewestCollectedCards => 'Lowest number of collected cards wins',
            self::FewestCollectedPiles => 'Lowest number of collected piles wins',
            self::PlayUntilOneHasCards => 'Play until only one player still has cards',
            self::FixedRounds => 'Play a set number of rounds',
        };
    }

    /**
     * Whether the game keeps running until every player but one has emptied their hand.
     */
    public function playsToLastPlayer(): bool
    {
        return $this === self::LastRemainingLoses || $this === self::PlayUntilOneHasCards;
    }
}
