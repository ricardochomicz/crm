<?php

namespace App\Http\Controllers\SubscriptionPlan;

use App\Http\Controllers\Controller;
use App\Actions\SubscriptionPlan\FilterSubscriptionPlanAction;
use App\Http\Resources\SubscriptionPlan\SubscriptionPlanResource;

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
        $plans = (new FilterSubscriptionPlanAction)->execute(['is_active' => true]);

        return ['data' => SubscriptionPlanResource::collection($plans)->resolve()];
    }
}
