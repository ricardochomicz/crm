<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUnit;
use App\Http\Resources\Tenant\TenantUnitResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListTenantUnitsController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $canManage = $request->user()->can('manageUnits', $tenant);
        $query = $tenant->units()->withCount(['members', 'teams']);
        if (! $canManage) {
            $query->whereHas('members', fn (Builder $members): Builder => $members->where('user_id', $request->user()->id));
        }

        return [
            'data' => $query->orderByDesc('is_headquarters')->orderBy('name')->orderBy('id')->get()->map(fn (TenantUnit $unit): array => (new TenantUnitResource($unit))->resolve($request))->all(),
            'meta' => [
                'can_manage' => $canManage,
            ],
        ];
    }
}
