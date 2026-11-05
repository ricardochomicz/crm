<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUnit;
use App\Models\TenantUser;
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
        if ($canManage) {
            $query->with(['members:id', 'teams:id,tenant_unit_id']);
        } else {
            $query->whereHas('members', fn (Builder $members): Builder => $members->where('user_id', $request->user()->id));
        }

        return [
            'data' => $query->orderByDesc('is_headquarters')->orderBy('name')->orderBy('id')->get()->map(fn (TenantUnit $unit): array => [
                'id' => $unit->id, 'name' => $unit->name, 'is_headquarters' => (bool) $unit->is_headquarters,
                'email' => $unit->email, 'phone' => $unit->phone, 'address' => $unit->address,
                'members_count' => $unit->members_count, 'teams_count' => $unit->teams_count,
                'membership_ids' => $canManage ? $unit->members->modelKeys() : [],
                'team_ids' => $canManage ? $unit->teams->modelKeys() : [],
            ])->all(),
            'meta' => [
                'can_manage' => $canManage,
                'members' => $canManage ? TenantUser::query()->where('tenant_id', $tenant->id)->with('user')->orderBy('id')->get()->map(fn (TenantUser $member): array => [
                    'id' => $member->id, 'name' => $member->user->name, 'status' => $member->status->value,
                ])->all() : [],
                'teams' => $canManage ? $tenant->teams()->orderBy('name')->get(['id', 'name', 'tenant_unit_id'])->toArray() : [],
            ],
        ];
    }
}
