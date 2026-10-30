<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Tenant\SelectTenantController;
use App\Http\Controllers\Tenant\ShowActiveTenantController;
use App\Http\Controllers\Tenant\StoreTenantMemberController;
use App\Http\Controllers\Tenant\UpdateTenantController;
use Illuminate\Support\Facades\Route;

Route::post('/api/v1/auth/login', LoginController::class)
    ->middleware('throttle:login')
    ->name('api.v1.auth.login');

Route::post('/api/v1/auth/logout', LogoutController::class)
    ->middleware('auth:web')
    ->name('api.v1.auth.logout');

Route::post('/api/v1/auth/register', RegisterController::class)
    ->middleware('throttle:5,1')
    ->name('api.v1.auth.register');

Route::put('/api/v1/active-tenant', SelectTenantController::class)
    ->middleware('auth:web')
    ->name('api.v1.active-tenant.update');

Route::get('/api/v1/active-tenant', ShowActiveTenantController::class)
    ->middleware(['auth:web', 'tenant.active'])
    ->name('api.v1.active-tenant.show');

Route::patch('/api/v1/active-tenant', UpdateTenantController::class)
    ->middleware(['auth:web', 'tenant.active'])
    ->name('api.v1.active-tenant.patch');

Route::post('/api/v1/tenant-members', StoreTenantMemberController::class)
    ->middleware(['auth:web', 'tenant.active', 'throttle:10,1'])
    ->name('api.v1.tenant-members.store');

Route::get('/reset-password/{token}', [PasswordController::class, 'show'])
    ->name('password.reset');

Route::post('/reset-password', [PasswordController::class, 'update'])
    ->middleware('throttle:10,1')
    ->name('password.update');

Route::post('/api/v1/auth/password/email', [PasswordController::class, 'sendLink'])
    ->middleware('throttle:5,1')
    ->name('api.v1.auth.password.email');
