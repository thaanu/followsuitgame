<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\GameTableController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\TurnController;
use App\Http\Middleware\ResolveGamePlayer;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/rules', 'Rules')->name('rules');

/*
|--------------------------------------------------------------------------
| Creating and joining
|--------------------------------------------------------------------------
*/

Route::get('/games/create', [GameController::class, 'create'])->name('games.create');

Route::post('/games', [GameController::class, 'store'])
    ->middleware('throttle:game-create')
    ->name('games.store');

Route::get('/games/join', [JoinController::class, 'index'])->name('games.join');

Route::get('/games/join/{code}', [JoinController::class, 'show'])->name('games.join.show');

Route::post('/games/join/{code}', [JoinController::class, 'store'])
    ->middleware('throttle:game-join')
    ->name('games.join.store');

/*
|--------------------------------------------------------------------------
| An individual game
|--------------------------------------------------------------------------
|
| Every route below resolves the acting player from their browser token before the
| controller runs. Authorisation itself lives in the controllers and services.
|
*/

Route::middleware(ResolveGamePlayer::class)->group(function (): void {
    Route::get('/games/{game}/lobby', [LobbyController::class, 'show'])->name('games.lobby');
    Route::post('/games/{game}/ready', [LobbyController::class, 'ready'])->name('games.ready');
    Route::post('/games/{game}/acknowledge-punishment', [LobbyController::class, 'acknowledgePunishment'])
        ->name('games.acknowledge-punishment');
    Route::post('/games/{game}/leave', [LobbyController::class, 'leave'])->name('games.leave');
    Route::delete('/games/{game}/players/{player}', [LobbyController::class, 'removePlayer'])
        ->name('games.players.remove');
    Route::put('/games/{game}/settings', [LobbyController::class, 'updateSettings'])->name('games.settings');
    Route::post('/games/{game}/start', [LobbyController::class, 'start'])->name('games.start');
    Route::post('/games/{game}/cancel', [GameController::class, 'cancel'])->name('games.cancel');

    Route::get('/games/{game}/table', [GameTableController::class, 'show'])->name('games.table');
    Route::get('/games/{game}/state', [GameTableController::class, 'state'])->name('games.state');
    Route::post('/games/{game}/heartbeat', [GameTableController::class, 'heartbeat'])->name('games.heartbeat');
    Route::get('/games/{game}/reconnect', [GameTableController::class, 'reconnect'])->name('games.reconnect');

    Route::middleware('throttle:game-moves')->group(function (): void {
        Route::post('/games/{game}/play', [TurnController::class, 'play'])->name('games.play');
        Route::post('/games/{game}/resolve-turn', [TurnController::class, 'resolve'])->name('games.resolve-turn');
    });

    Route::get('/games/{game}/results', [ResultsController::class, 'show'])->name('games.results');
    Route::post('/games/{game}/rematch', [ResultsController::class, 'rematch'])->name('games.rematch');
});
