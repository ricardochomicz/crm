<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateTenantRolePermissionsAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /** @param list<string> $permissions */
    public function handle(User $actor, Tenant $tenant, TenantUserRole $role, array $permissions, int $version): void
    {
        abort_if($role === TenantUserRole::Owner, 403, 'As permissões do proprietário são protegidas.');
        DB::transaction(function () use ($actor, $tenant, $role, $permissions, $version): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            Gate::forUser($actor)->authorize('managePermissions', $tenant);
            $subscription = $tenant->subscription()->lockForUpdate()->first();
            abort_unless($subscription?->allowsOperationalWrites(), 403, 'A assinatura não permite alterar permissões.');
            $previous = $this->permissions->forRole($tenant, $role);
            abort_if($previous['version'] !== $version, 409, 'As permissões foram alteradas em outra sessão. Recarregue antes de salvar.');
            sort($permissions);
            $before = $previous['permissions'];
            sort($before);
            if ($before === $permissions) {
                return;
            }
            $now = now();
            DB::table('tenant_role_permissions')->updateOrInsert(
                ['tenant_id' => $tenant->getKey(), 'role' => $role->value],
                ['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'version' => $version + 1,
                    'updated_at' => $now, ...($version === 0 ? ['created_at' => $now] : [])],
            );
            DB::table('tenant_permission_changes')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenant->getKey(),
                'actor_user_id' => $actor->getKey(), 'role' => $role->value,
                'previous_permissions' => json_encode($before, JSON_THROW_ON_ERROR),
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'version' => $version + 1, 'created_at' => $now,
            ]);
        }, attempts: 5);
    }
}
