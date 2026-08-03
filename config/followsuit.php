<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Game lifetime
    |--------------------------------------------------------------------------
    |
    | How long a game stays alive before the cleanup command cancels it, and how
    | long a completed game's record is kept before it is deleted entirely.
    |
    */

    'game_lifetime_hours' => (int) env('FOLLOW_SUIT_GAME_LIFETIME_HOURS', 24),

    'purge_completed_after_days' => (int) env('FOLLOW_SUIT_PURGE_AFTER_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    |
    | How often the browser pings the server to say it is still there, and how long
    | a player may go quiet before they are treated as disconnected.
    |
    */

    'heartbeat_seconds' => (int) env('FOLLOW_SUIT_HEARTBEAT_SECONDS', 20),

    'presence_timeout_seconds' => (int) env('FOLLOW_SUIT_PRESENCE_TIMEOUT_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Per-minute caps on the endpoints that create rows or take turns.
    |
    */

    'rate_limits' => [
        'create' => (int) env('FOLLOW_SUIT_RATE_CREATE', 10),
        'join' => (int) env('FOLLOW_SUIT_RATE_JOIN', 20),
        'moves' => (int) env('FOLLOW_SUIT_RATE_MOVES', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Punishment safety notice
    |--------------------------------------------------------------------------
    |
    | Shown wherever a punishment is configured or displayed.
    |
    */

    'punishment_notice' => 'Punishments must be safe, legal, respectful and agreed by everyone playing. '
        .'Nothing dangerous, humiliating, harassing, illegal, coercive, discriminatory, sexual, '
        .'or involving alcohol or other substances.',

];
