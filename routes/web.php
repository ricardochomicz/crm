<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Support\Facades\Route;

Route::post('/api/v1/auth/login', LoginController::class)
    ->middleware('throttle:login')
    ->name('api.v1.auth.login');

Route::post('/api/v1/auth/logout', LogoutController::class)
    ->middleware('auth:web')
    ->name('api.v1.auth.logout');
