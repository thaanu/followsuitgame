<?php

namespace App\Game\Support;

use Illuminate\Support\Str;

/**
 * The opaque token a browser keeps to prove it owns a seat.
 *
 * The plain token is only ever held by the client; the database stores its SHA-256
 * hash, so a database leak cannot be used to impersonate a player.
 */
final class PlayerToken
{
    public const LENGTH = 64;

    public static function generate(): string
    {
        return Str::random(self::LENGTH);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{'.self::LENGTH.'}$/', $token) === 1;
    }
}
