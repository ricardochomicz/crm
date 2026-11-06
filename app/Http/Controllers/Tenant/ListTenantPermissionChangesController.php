<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantPermissionChangesRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ListTenantPermissionChangesController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(ListTenantPermissionChangesRequest $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        Gate::forUser($request->user())->authorize('managePermissions', $tenant);
        $filters = $request->validated();

        return DB::table('tenant_permission_changes as changes')
            ->join('users', 'users.id', '=', 'changes.actor_user_id')
            ->where('changes.tenant_id', $tenant->getKey())
            ->when(isset($filters['role']), fn ($query) => $query->where('changes.role', $filters['role']))
            ->orderByDesc('changes.created_at')->orderByDesc('changes.id')
            ->paginate(20, ['changes.*', 'users.name as actor_name'])
            ->through(fn (object $change): array => [
                'id' => $change->id, 'actor_name' => $change->actor_name,
                'role' => $change->role, 'version' => $change->version,
                'previous_permissions' => json_decode($change->previous_permissions, true, flags: JSON_THROW_ON_ERROR),
                'permissions' => json_decode($change->permissions, true, flags: JSON_THROW_ON_ERROR),
                'created_at' => Carbon::parse($change->created_at)->toIso8601String(),
            ])->toArray();
    }
}
