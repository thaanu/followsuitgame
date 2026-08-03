<?php

namespace App\Enums;

enum CardLocation: string
{
    case Deck = 'deck';
    case PlayerHand = 'player_hand';
    case ActivePile = 'active_pile';
    case CollectedCards = 'collected_cards';
    case Discarded = 'discarded';
    case Removed = 'removed';
}
