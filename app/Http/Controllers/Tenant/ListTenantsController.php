<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ListTenantsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        return $request->user()
            ->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->orderBy('tenants.name')
            ->orderBy('tenants.id')
            ->paginate(20)
            ->through(fn(Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->type->value,
                'role' => $tenant->membership->role->value
            ])
            ->toArray();
    }
}
