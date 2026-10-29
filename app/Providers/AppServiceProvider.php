<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');

            $normalizedEmail = is_string($email)
                ? Str::lower(trim($email))
                : '';

            return [
                Limit::perMinute(30)
                    ->by('login-ip:' . $request->ip()),

                Limit::perMinute(5)
                    ->by('login-account:' . hash(
                        'sha256',
                        $normalizedEmail . '|' . $request->ip()
                    ))
            ];
        });
    }
}
