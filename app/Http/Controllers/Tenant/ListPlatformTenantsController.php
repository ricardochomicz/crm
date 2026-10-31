<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListPlatformTenantsRequest;
use App\Models\Tenant;

class ListPlatformTenantsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListPlatformTenantsRequest $request): array
    {
        $filters = $request->validated();

        $query = Tenant::query()
            ->with('subscription.plan:id,code,name')
            ->withCount('users');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['document'])) {
            $query->where('document', $filters['document']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters)
            ->through(function (Tenant $tenant): array {
                $subscription = $tenant->subscription;

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'type' => $tenant->type->value,
                    'document' => $tenant->document,
                    'status' => $tenant->status->value,
                    'users_count' => $tenant->users_count,
                    'subscription' => $subscription === null ? null : [
                        'status' => $subscription->status->value,
                        'plan_name' => $subscription->plan?->name,
                        'allows_operational_writes' => $subscription
                            ->allowsOperationalWrites(),
                    ],
                    'created_at' => $tenant->created_at
                        ?->toIso8601String(),
                ];
            })
            ->toArray();
    }
}
