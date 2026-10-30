<?php

use App\Http\Controllers\SubscriptionPlan\ListSubscriptionPlansController;
use App\Http\Controllers\Tenant\ListTenantsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/me', function (Request $request): array {
        $user = $request->user();

        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ],
        ];
    })->middleware('auth:sanctum')->name('api.v1.me');

    Route::get('/tenants', ListTenantsController::class)
        ->middleware('auth:sanctum')
        ->name('api.v1.tenants.index');

    Route::get('/subscription-plans', ListSubscriptionPlansController::class)
        ->middleware('auth:sanctum')
        ->name('api.v1.subscription-plans.index');
});
