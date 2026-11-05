<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantInvitationStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ShowActiveTenantController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(Request $request, ResolveTenantPermissionsAction $permissions): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $grants = $permissions->handle($request->user(), $tenant);
        $subscription = $tenant->subscription()->first();
        $canManageTeam = array_intersect($grants, ['members.view', 'invitations.view']) !== [];
        $capacity = null;
        if ($canManageTeam && $subscription !== null) {
            $users = $tenant->users()->count();
            $reserved = $tenant->invitations()->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())->count();
            $capacity = [
                'limits' => ['users' => $subscription->user_limit],
                'usage' => ['users' => $users, 'reserved_invitations' => $reserved],
                'remaining' => ['users' => max(0, $subscription->user_limit - $users - $reserved)],
            ];
        }

        return ['data' => [
            'id' => $tenant->id, 'name' => $tenant->name, 'type' => $tenant->type->value,
            'role' => $tenant->membership->role->value,
            'document' => $tenant->document,
            'trade_name' => $tenant->trade_name,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'timezone' => $tenant->timezone,
            'logo_url' => $tenant->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo) : null,
            'access' => [
                'permissions' => $grants,
                'can_manage_permissions' => $tenant->membership->role === TenantUserRole::Owner,
                'allows_operational_writes' => $subscription?->allowsOperationalWrites() ?? false,
                'assignable_roles' => array_values(array_map(fn (TenantUserRole $role): string => $role->value,
                    array_filter(TenantUserRole::cases(), fn (TenantUserRole $role): bool => $permissions->canAssign($request->user(), $tenant, $role)))),
                'capacity' => $capacity,
            ],
        ]];
    }
}
