<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\SubscriptionPlan\ListSubscriptionPlansController;
use App\Http\Controllers\Team\StoreTeamController;
use App\Http\Controllers\Tenant\ListTenantMembersController;
use App\Http\Controllers\Tenant\ListTenantsController;
use App\Http\Controllers\Tenant\SelectTenantController;
use App\Http\Controllers\Tenant\ShowActiveTenantController;
use App\Http\Controllers\Tenant\ShowTenantSubscriptionController;
use App\Http\Controllers\Tenant\StoreTenantController;
use App\Http\Controllers\Tenant\StoreTenantMemberController;
use App\Http\Controllers\Tenant\UpdateTenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', LoginController::class)
            ->middleware('throttle:login')
            ->name('login');

        Route::post('/register', RegisterController::class)
            ->middleware('throttle:5,1')
            ->name('register');

        Route::post('/password/email', [PasswordController::class, 'sendLink'])
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::post('/logout', LogoutController::class)
            ->middleware('auth:sanctum')
            ->name('logout');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', function (Request $request): array {
            $user = $request->user();

            return [
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ];
        })->name('me');

        Route::get('/subscription-plans', ListSubscriptionPlansController::class)
            ->name('subscription-plans.index');

        Route::get('/tenants', ListTenantsController::class)
            ->name('tenants.index');

        Route::post('/tenants', StoreTenantController::class)
            ->middleware('throttle:5,1')
            ->name('tenants.store');

        Route::put('/active-tenant', SelectTenantController::class)
            ->name('active-tenant.update');

        Route::get('/active-tenant', ShowActiveTenantController::class)
            ->middleware('tenant.active:selected')
            ->name('active-tenant.show');

        Route::prefix('tenants/{tenantId}')
            ->whereUuid('tenantId')
            ->middleware('tenant.active')
            ->group(function (): void {
                Route::get('/', ShowActiveTenantController::class)
                    ->name('tenants.show');

                Route::get('/members', ListTenantMembersController::class)
                    ->name('tenant-members.index');

                Route::get('/subscription', ShowTenantSubscriptionController::class)
                    ->name('tenants.subscription.show');

                Route::middleware('subscription.writable')
                    ->group(function (): void {
                        Route::patch('/', UpdateTenantController::class)
                            ->name('tenants.update');

                        Route::post('/members', StoreTenantMemberController::class)
                            ->middleware('throttle:10,1')
                            ->name('tenant-members.store');

                        Route::post('/teams', StoreTeamController::class)
                            ->name('teams.store');
                    });
            });
    });
});
