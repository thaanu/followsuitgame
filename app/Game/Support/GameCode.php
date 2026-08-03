<?php

namespace App\Game\Support;

/**
 * Six-character game codes drawn from an alphabet with no visually ambiguous
 * characters, so codes can be read aloud or typed from a photo without confusion.
 */
final class GameCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    public static function generate(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function isWellFormed(string $code): bool
    {
        return preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', self::normalise($code)) === 1;
    }
}
