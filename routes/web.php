<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Tenant\SelectTenantController;
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
