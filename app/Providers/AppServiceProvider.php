<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('system-register', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        RateLimiter::for('tenant-login', function (Request $request): array {
            $email = mb_strtolower((string) $request->input('email', 'guest'));
            $tenant = (string) $request->route('tenant', 'unknown');

            return [
                Limit::perMinute(5)->by($tenant.'|'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by($tenant.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('tenant-api', function (Request $request): array {
            $tenant = (string) (tenant('id') ?? $request->route('tenant', 'unknown'));
            $identifier = (string) ($request->user('api')?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perMinute(120)->by($tenant.'|'.$identifier),
            ];
        });
    }
}
