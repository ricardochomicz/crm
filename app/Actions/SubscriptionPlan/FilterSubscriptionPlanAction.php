<?php
namespace App\Actions\SubscriptionPlan;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;
class FilterSubscriptionPlanAction
{
    /** @param array<string, mixed> $filters */
    public function execute(array $filters): Collection
    {
        return SubscriptionPlan::query()->filter($filters)->orderBy('monthly_price')->orderBy('id')->get(['*']);
    }
}
