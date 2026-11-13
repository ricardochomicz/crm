<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

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
        if ($this->app->runningUnitTests()) {
            $default = config('database.default');
            $connection = config("database.connections.{$default}");

            if ($default !== 'sqlite'
                || ! is_array($connection)
                || ($connection['database'] ?? null) !== ':memory:'
                || filled($connection['url'] ?? null)) {
                throw new \RuntimeException(
                    'Testes bloqueados: configure SQLite em memória e remova DB_URL antes de executar testes.'
                );
            }
        }

        Gate::define(
            'access-platform-admin',
            fn(User $user): bool => $user->is_platform_admin === true
        );

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
