<?php

namespace App\Enums;

enum GameActionType: string
{
    case GameCreated = 'game_created';
    case PlayerJoined = 'player_joined';
    case PlayerLeft = 'player_left';
    case PlayerReady = 'player_ready';
    case PunishmentAcknowledged = 'punishment_acknowledged';
    case SettingsUpdated = 'settings_updated';
    case GameStarted = 'game_started';
    case CardPlayed = 'card_played';
    case PileCollected = 'pile_collected';
    case CardsDrawn = 'cards_drawn';
    case TurnSkipped = 'turn_skipped';
    case TurnTimedOut = 'turn_timed_out';
    case DirectionReversed = 'direction_reversed';
    case SuitChanged = 'suit_changed';
    case PileCleared = 'pile_cleared';
    case ExtraTurnGranted = 'extra_turn_granted';
    case PlayerDisconnected = 'player_disconnected';
    case PlayerReconnected = 'player_reconnected';
    case PlayerFinished = 'player_finished';
    case RoundCompleted = 'round_completed';
    case GameCompleted = 'game_completed';
    case GameCancelled = 'game_cancelled';
    case PunishmentAssigned = 'punishment_assigned';
}
