<?php

use App\Http\Middleware\EnsureActiveTenant;
use App\Http\Middleware\EnsureSubscriptionAllowsWrites;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*')) {
                abort(401, 'Não autenticado.');
            }
            return null;
        });
        $middleware->alias([
            'tenant.active' => EnsureActiveTenant::class,
            'subscription.writable' => EnsureSubscriptionAllowsWrites::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
