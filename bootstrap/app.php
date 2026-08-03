<?php

use App\Game\Exceptions\GameException;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A rule or lifecycle violation is something the player should be told about,
        // not a server error: send them back with the reason attached.
        $exceptions->render(function (GameException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'reason' => $exception->reason,
                    'message' => $exception->getMessage(),
                ], $exception->status);
            }

            return back()->with('gameError', [
                'reason' => $exception->reason,
                'message' => $exception->getMessage(),
            ]);
        });
    })->create();
