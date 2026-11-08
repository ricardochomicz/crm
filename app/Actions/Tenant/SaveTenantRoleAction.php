<?php

namespace App\Actions\Tenant;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveTenantRoleAction
{
    /** @param array{name: string, permissions: list<string>, code?: string, legacy_role?: string, is_active?: bool, version?: int} $data */
    public function execute(User $actor, Tenant $tenant, array $data, ?string $id = null): TenantRole
    {
        return DB::transaction(function () use ($actor, $tenant, $data, $id): TenantRole {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            Gate::forUser($actor)->authorize('managePermissions', $tenant);
            abort_unless($tenant->subscription()->lockForUpdate()->first()?->allowsOperationalWrites(), 403, 'A assinatura não permite alterar permissões.');

            $role = $id === null
                ? new TenantRole(['tenant_id' => $tenant->id])
                : $tenant->roles()->lockForUpdate()->findOrFail($id);

            if ($role->exists && $role->code === 'OWNER') {
                throw ValidationException::withMessages(['role' => 'As permissões do proprietário são protegidas.']);
            }
            abort_if($role->exists && (int) ($data['version'] ?? 0) !== $role->version, 409, 'Este cargo foi alterado em outra sessão. Recarregue antes de salvar.');

            $code = $role->exists ? $role->code : ($data['code'] ?? Str::upper(Str::slug($data['name'], '_')));
            if ($code === 'OWNER' || strlen($code) > 40 || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $code)) {
                throw ValidationException::withMessages(['code' => 'Informe um código válido de até 40 caracteres, diferente de OWNER.']);
            }
            if (! $role->exists && $tenant->roles()->where('code', $code)->exists()) {
                throw ValidationException::withMessages(['code' => 'Este código já existe nesta empresa.']);
            }

            $permissions = array_values(array_unique($data['permissions']));
            sort($permissions);
            foreach (TenantPermission::catalog() as $item) {
                if (in_array($item['key'], $permissions, true) && array_diff($item['requires'], $permissions) !== []) {
                    throw ValidationException::withMessages(['permissions' => 'Inclua as permissões necessárias para '.$item['label'].'.']);
                }
            }

            $before = $role->exists ? $this->state($role) : null;
            $role->fill(['name' => $data['name'], 'permissions' => $permissions]);
            if (! $role->exists) {
                $role->fill(['code' => $code, 'legacy_role' => $data['legacy_role'] ?? 'ASSISTANT', 'is_system' => false, 'is_active' => true]);
            }
            if (array_key_exists('is_active', $data)) {
                $role->is_active = $data['is_active'];
            }
            $after = $this->state($role);
            if ($before === $after) {
                return $role;
            }

            $role->version = $role->exists ? $role->version + 1 : 1;
            $role->save();
            DB::table('tenant_permission_changes')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $role->id,
                'actor_user_id' => $actor->id,
                'role' => $role->legacy_role,
                'previous_permissions' => json_encode($before['permissions'] ?? [], JSON_THROW_ON_ERROR),
                'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'previous_role_state' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                'role_state' => json_encode($after, JSON_THROW_ON_ERROR),
                'version' => $role->version,
                'created_at' => now(),
            ]);

            return $role;
        }, attempts: 5);
    }

    /** @return array{name: string, is_active: bool, permissions: list<string>} */
    private function state(TenantRole $role): array
    {
        $permissions = $role->effectivePermissions();
        sort($permissions);

        return ['name' => $role->name, 'is_active' => $role->is_active, 'permissions' => $permissions];
    }
}
