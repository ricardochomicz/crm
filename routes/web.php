<?php

use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\ShowTenantInvitationController;

Route::get('/reset-password/{token}', [PasswordController::class, 'show'])
    ->name('password.reset');

Route::post('/reset-password', [PasswordController::class, 'update'])
    ->middleware('throttle:10,1')
    ->name('password.update');

Route::get('/invitations/{token}', ShowTenantInvitationController::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:30,1')
    ->name('tenant-invitations.show');
