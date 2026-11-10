<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Support\Permissions\DefaultTenantRolePermissions;
use Illuminate\Database\Seeder;

/**
 * Aplica o perfil padrão de Consultor em todos os tenants existentes.
 * ATENÇÃO: substitui as permissões personalizadas do cargo CONSULTANT.
 * Não altera outros cargos ou vínculos de usuários.
 */
class ConsultantPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $definition = DefaultTenantRolePermissions::all()['CONSULTANT'];
        $updated = 0;

        Tenant::query()->chunkById(100, function ($tenants) use ($definition, &$updated): void {
            foreach ($tenants as $tenant) {
                $role = TenantRole::query()->firstOrCreate(
                    ['tenant_id' => $tenant->getKey(), 'code' => 'CONSULTANT'],
                    [
                        'name' => $definition['name'],
                        'permissions' => $definition['permissions'],
                        'legacy_role' => 'CONSULTANT',
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );

                if (! $role->wasRecentlyCreated && $role->permissions !== $definition['permissions']) {
                    $role->permissions = $definition['permissions'];
                    $role->save();
                }

                $updated++;
            }
        });

        $this->command?->info("Perfil Consultor aplicado a {$updated} empresa(s).");
    }
}
