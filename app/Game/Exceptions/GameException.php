<?php

namespace App\Game\Exceptions;

use RuntimeException;

/**
 * A rule or lifecycle violation that the player should be told about, rather than a
 * bug. Each case carries a stable reason code so the frontend can render the right
 * screen or message.
 */
class GameException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidInvitation(): self
    {
        return new self('invalid_invitation', 'This invitation link is not valid.', 404);
    }

    public static function expiredInvitation(): self
    {
        return new self('expired_invitation', 'This invitation has expired.', 410);
    }

    public static function alreadyStarted(): self
    {
        return new self('already_started', 'This game has already started.', 409);
    }

    public static function gameFull(): self
    {
        return new self('game_full', 'This game is already full.', 409);
    }

    public static function gameCancelled(): self
    {
        return new self('game_cancelled', 'This game was cancelled.', 410);
    }

    public static function gameCompleted(): self
    {
        return new self('game_completed', 'This game has already finished.', 410);
    }

    public static function nicknameTaken(): self
    {
        return new self('nickname_taken', 'Somebody in this game is already using that nickname.');
    }

    public static function notInGame(): self
    {
        return new self('not_in_game', 'You are not part of this game.', 403);
    }

    public static function notHost(): self
    {
        return new self('not_host', 'Only the host can do that.', 403);
    }

    public static function notYourTurn(): self
    {
        return new self('not_your_turn', 'It is not your turn.', 409);
    }

    public static function staleState(): self
    {
        return new self('stale_state', 'The game has moved on since your last update.', 409);
    }

    public static function duplicateAction(): self
    {
        return new self('duplicate_action', 'That action has already been processed.', 409);
    }

    public static function cardNotOwned(): self
    {
        return new self('card_not_owned', 'That card is not in your hand.', 403);
    }

    public static function illegalMove(string $message = 'You cannot play that card.'): self
    {
        return new self('illegal_move', $message);
    }

    public static function gameNotPlayable(): self
    {
        return new self('game_not_playable', 'This game is not accepting moves right now.', 409);
    }

    public static function cannotStart(string $message): self
    {
        return new self('cannot_start', $message);
    }
}
