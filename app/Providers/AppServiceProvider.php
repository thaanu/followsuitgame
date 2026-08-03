<?php

namespace App\Providers;

use App\Game\Services\ReconnectionService;
use App\Game\Support\PlayerToken;
use App\Models\GamePlayer;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePlayerGuard();
        $this->configureRateLimiting();
    }

    /**
     * Authenticate Follow Suit players from the opaque token their browser holds.
     *
     * Only the SHA-256 hash of the token is stored, so a leaked database row cannot
     * be replayed to impersonate a player.
     */
    protected function configurePlayerGuard(): void
    {
        Auth::viaRequest('player-token', function (Request $request): ?GamePlayer {
            $token = (string) $request->header(ReconnectionService::TOKEN_HEADER, '');

            if ($token === '') {
                $token = (string) $request->input('player_token', '');
            }

            if (! PlayerToken::isWellFormed($token)) {
                return null;
            }

            return GamePlayer::where('secure_token_hash', PlayerToken::hash($token))
                ->whereNull('left_at')
                ->first();
        });
    }

    /**
     * Throttle the endpoints that create rows or advance a turn.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('game-create', fn (Request $request) => Limit::perMinute(
            (int) config('followsuit.rate_limits.create', 10)
        )->by($request->ip()));

        RateLimiter::for('game-join', fn (Request $request) => Limit::perMinute(
            (int) config('followsuit.rate_limits.join', 20)
        )->by($request->ip()));

        RateLimiter::for('game-moves', fn (Request $request) => Limit::perMinute(
            (int) config('followsuit.rate_limits.moves', 120)
        )->by($request->header(ReconnectionService::TOKEN_HEADER) ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
