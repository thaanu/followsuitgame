<?php

namespace App\Enums;

enum SpecialCardEffect: string
{
    case AceChangesSuit = 'ace_changes_suit';
    case KingReversesDirection = 'king_reverses_direction';
    case QueenSkipsNext = 'queen_skips_next';
    case JackExtraTurn = 'jack_extra_turn';
    case JokerWildSuit = 'joker_wild_suit';
    case ClearPile = 'clear_pile';

    public function label(): string
    {
        return match ($this) {
            self::AceChangesSuit => 'Ace changes the active suit',
            self::KingReversesDirection => 'King reverses the turn direction',
            self::QueenSkipsNext => 'Queen skips the next player',
            self::JackExtraTurn => 'Jack grants another turn',
            self::JokerWildSuit => 'Joker acts as any suit',
            self::ClearPile => 'A chosen rank clears the pile',
        };
    }

    /**
     * The rank that triggers this effect, when it is fixed.
     */
    public function trigger(): ?CardRank
    {
        return match ($this) {
            self::AceChangesSuit => CardRank::Ace,
            self::KingReversesDirection => CardRank::King,
            self::QueenSkipsNext => CardRank::Queen,
            self::JackExtraTurn => CardRank::Jack,
            self::JokerWildSuit => CardRank::Joker,
            self::ClearPile => null,
        };
    }
}
