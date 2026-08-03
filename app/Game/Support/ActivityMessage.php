<?php

namespace App\Game\Support;

use App\Enums\CardSuit;
use App\Enums\TurnDirection;
use App\Game\Data\PunishmentConfiguration;
use App\Models\GameCard;
use App\Models\GamePlayer;

/**
 * Human readable activity feed strings.
 *
 * These are shown to every player, so they must never mention a card that is still
 * hidden — only cards that have been played face up, or counts.
 */
final class ActivityMessage
{
    public static function cardPlayed(GamePlayer $player, GameCard $card): string
    {
        return "{$player->nickname} played the {$card->label()}.";
    }

    public static function couldNotFollow(GamePlayer $player, CardSuit $suit, int $cardsCollected): string
    {
        return "{$player->nickname} could not follow {$suit->label()} and collected {$cardsCollected} ".
            self::plural($cardsCollected, 'card', 'cards').'.';
    }

    public static function collectedPile(GamePlayer $player, int $cardsCollected): string
    {
        return "{$player->nickname} collected the pile of {$cardsCollected} ".
            self::plural($cardsCollected, 'card', 'cards').'.';
    }

    public static function wonPile(GamePlayer $player, int $cardsCollected): string
    {
        return "{$player->nickname} took the completed pile of {$cardsCollected} ".
            self::plural($cardsCollected, 'card', 'cards').'.';
    }

    public static function drewCards(GamePlayer $player, int $count): string
    {
        return "{$player->nickname} could not follow suit and drew {$count} ".
            self::plural($count, 'card', 'cards').'.';
    }

    public static function turnSkipped(GamePlayer $player): string
    {
        return "{$player->nickname} could not follow suit and skipped their turn.";
    }

    public static function skippedByQueen(GamePlayer $player): string
    {
        return "{$player->nickname} was skipped.";
    }

    public static function suitChanged(GamePlayer $player, CardSuit $suit): string
    {
        return "{$player->nickname} changed the active suit to {$suit->label()}.";
    }

    public static function directionReversed(TurnDirection $direction): string
    {
        return "The turn direction has been reversed to {$direction->label()}.";
    }

    public static function extraTurn(GamePlayer $player): string
    {
        return "{$player->nickname} plays again.";
    }

    public static function pileCleared(int $count): string
    {
        return "The pile of {$count} ".self::plural($count, 'card was', 'cards were').' cleared away.';
    }

    public static function cardsRemaining(GamePlayer $player, int $remaining): string
    {
        return "{$player->nickname} has {$remaining} ".self::plural($remaining, 'card', 'cards').' remaining.';
    }

    public static function playerFinished(GamePlayer $player, int $position): string
    {
        return "{$player->nickname} finished in ".self::ordinal($position).' place.';
    }

    public static function playerJoined(GamePlayer $player): string
    {
        return "{$player->nickname} joined the game.";
    }

    public static function playerLeft(GamePlayer $player): string
    {
        return "{$player->nickname} left the game.";
    }

    public static function playerReady(GamePlayer $player, bool $ready): string
    {
        return $ready
            ? "{$player->nickname} is ready."
            : "{$player->nickname} is no longer ready.";
    }

    public static function punishmentAcknowledged(GamePlayer $player): string
    {
        return "{$player->nickname} agreed to the punishment.";
    }

    public static function gameStarted(int $cardsEach): string
    {
        return "The game has started. Everyone was dealt {$cardsEach} ".
            self::plural($cardsEach, 'card', 'cards').'.';
    }

    public static function disconnected(GamePlayer $player): string
    {
        return "{$player->nickname} lost connection.";
    }

    public static function reconnected(GamePlayer $player): string
    {
        return "{$player->nickname} reconnected.";
    }

    public static function timedOut(GamePlayer $player): string
    {
        return "{$player->nickname} ran out of time.";
    }

    public static function roundCompleted(int $roundNumber): string
    {
        return "Round {$roundNumber} is complete. Cards are being dealt again.";
    }

    public static function gameCompleted(?GamePlayer $winner): string
    {
        return $winner === null
            ? 'The game is over.'
            : "{$winner->nickname} won the game!";
    }

    public static function gameCancelled(): string
    {
        return 'The host cancelled the game.';
    }

    public static function punishmentAssigned(GamePlayer $player, PunishmentConfiguration $punishment, string $reason): string
    {
        return "{$player->nickname} {$reason} and receives the punishment: {$punishment->title}.";
    }

    public static function ordinal(int $number): string
    {
        $suffix = match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix;
    }

    private static function plural(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }
}
