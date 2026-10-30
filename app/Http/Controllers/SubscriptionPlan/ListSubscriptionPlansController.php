<?php

namespace App\Http\Controllers\SubscriptionPlan;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;

class ListSubscriptionPlansController extends Controller
{
    /**
     * @return array{
     *     data: list<array{
     *         id: string,
     *         code: string,
     *         name: string,
     *         description: string|null,
     *         monthly_price: string,
     *         currency: string,
     *         user_limit: int,
     *         storage_limit_mb: int
     *     }>
     * }
     */
    public function __invoke(): array
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->orderBy('id')
            ->get();

        return [
            'data' => $plans->map(
                fn(SubscriptionPlan $plan): array => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'monthly_price' => $plan->monthly_price,
                    'currency' => $plan->currency,
                    'user_limit' => $plan->user_limit,
                    'storage_limit_mb' => $plan->storage_limit_mb,
                ],
            )->all(),
        ];
    }
}
