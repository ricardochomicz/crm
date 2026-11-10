<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Support\Permissions\DefaultTenantRolePermissions;

/**
 * Cria os cargos padrão na criação de um tenant.
 * Idempotente: não substitui personalizações já salvas.
 *
 * A coluna legacy_role é preenchida somente para compatibilidade com
 * o schema atual e deve ser removida na migração definitiva de RBAC.
 */
class SeedTenantRolesAction
{
    private const LEGACY_COMPATIBILITY = [
        'OWNER' => 'OWNER',
        'MANAGER' => 'MANAGER',
        'SUPERVISOR' => 'CONSULTANT',
        'CONSULTANT' => 'CONSULTANT',
        'TELEMARKETING' => 'CONSULTANT',
        'ADMINISTRATIVE' => 'ASSISTANT',
        'BACKOFFICE' => 'ASSISTANT',
        'FINANCE' => 'ASSISTANT',
    ];

    public function execute(Tenant $tenant): void
    {
        foreach (DefaultTenantRolePermissions::all() as $code => $definition) {
            TenantRole::query()->firstOrCreate(
                ['tenant_id' => $tenant->getKey(), 'code' => $code],
                [
                    'name' => $definition['name'],
                    'permissions' => $definition['permissions'],
                    'legacy_role' => self::LEGACY_COMPATIBILITY[$code],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
