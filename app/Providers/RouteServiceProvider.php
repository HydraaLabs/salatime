<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user() instanceof \App\Models\Mobile\MobileAccount ? 'mobile:'.$request->user()->id : ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            $limits = [Limit::perMinute(10)->by('mobile-login-ip:'.$request->ip())];
            if ($request->filled('email')) {
                $limits[] = Limit::perMinute(5)->by('mobile-login-email:'.hash('sha256', mb_strtolower((string) $request->input('email'))).':'.$request->ip());
            }

            return $limits;
        });
        RateLimiter::for('mobile-email', fn (Request $request) => [
            Limit::perMinute(3)->by('mobile-mail-ip:'.$request->ip()),
            Limit::perHour(5)->by('mobile-mail-email:'.hash('sha256', mb_strtolower((string) ($request->user()?->email ?? $request->input('email', ''))))),
        ]);
        RateLimiter::for('mobile-code', fn (Request $request) => [
            Limit::perMinute(10)->by('mobile-code-ip:'.$request->ip()),
            Limit::perMinute(5)->by('mobile-code-email:'.hash('sha256', mb_strtolower((string) ($request->user()?->email ?? $request->input('email', ''))))),
        ]);
        RateLimiter::for('mobile-preferences', fn (Request $request) => Limit::perMinute(30)->by('mobile-prefs:'.$request->user()->id));

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
