<?php

namespace App\Support\Permissions;

use App\Enums\TenantPermission;
use InvalidArgumentException;

/**
 * Perfis iniciais dos oito cargos da empresa.
 *
 * O administrador da plataforma não é um TenantRole.
 * As permissões controlam operações; o alcance dos dados deve ser
 * imposto separadamente pelas policies e queries.
 */
final class DefaultTenantRolePermissions
{
    /** @return array<string, array{name: string, permissions: list<string>}> */
    public static function all(): array
    {
        $consultant = [
            'clients.view', 'clients.create', 'clients.update',
            'opportunities.view', 'opportunities.create', 'opportunities.update',
            'orders.view', 'orders.manage',
            'portfolio.view',
            'calendar.view', 'calendar.create', 'calendar.update', 'calendar.delete',
            'after-sales.view', 'after-sales.create', 'after-sales.update',
            'products.view', 'operator-classifications.view',
            'commercial-goals.view', 'commissions.view',
        ];

        $supervisor = [
            ...$consultant,
            'calendar.team.view', 'teams.view', 'members.view',
            'team-commissions.view',
        ];

        $manager = [
            ...$supervisor,
            'clients.delete', 'commercial-goals.manage',
            'commissions.manage', 'team-commissions.manage',
            'operator-classifications.manage',
            'products.create', 'products.update', 'products.promotions',
            'members.create', 'members.profile.view', 'members.profile.update',
            'members.team', 'members.status',
            'teams.create', 'teams.update', 'teams.supervisor',
            'invitations.view', 'invitations.create', 'invitations.resend', 'invitations.revoke',
        ];

        $administrative = [
            'clients.view', 'clients.create', 'clients.update',
            'orders.view', 'orders.manage', 'portfolio.view',
            'calendar.view', 'calendar.create', 'calendar.update',
            'after-sales.view', 'after-sales.create', 'after-sales.update',
            'products.view', 'operator-classifications.view',
            'members.view', 'members.profile.view',
        ];

        $telemarketing = [
            'clients.view', 'clients.create', 'clients.update',
            'opportunities.view', 'opportunities.create', 'opportunities.update',
            'calendar.view', 'calendar.create', 'calendar.update',
            'products.view', 'operator-classifications.view',
        ];

        $backoffice = [
            'clients.view', 'clients.update',
            'opportunities.view', 'orders.view', 'orders.manage',
            'portfolio.view', 'after-sales.view', 'after-sales.create',
            'after-sales.update', 'products.view', 'operator-classifications.view',
        ];

        $finance = [
            'clients.view', 'orders.view', 'portfolio.view',
            'commissions.view', 'commissions.manage',
            'team-commissions.view', 'team-commissions.manage',
            'commercial-goals.view', 'subscription.view',
        ];

        return [
            'OWNER' => ['name' => 'Proprietário', 'permissions' => self::validated(TenantPermission::keys())],
            'MANAGER' => ['name' => 'Gerente', 'permissions' => self::validated($manager)],
            'SUPERVISOR' => ['name' => 'Supervisor', 'permissions' => self::validated($supervisor)],
            'CONSULTANT' => ['name' => 'Consultor', 'permissions' => self::validated($consultant)],
            'ADMINISTRATIVE' => ['name' => 'Administrativo', 'permissions' => self::validated($administrative)],
            'TELEMARKETING' => ['name' => 'Telemarketing', 'permissions' => self::validated($telemarketing)],
            'BACKOFFICE' => ['name' => 'Backoffice', 'permissions' => self::validated($backoffice)],
            'FINANCE' => ['name' => 'Financeiro', 'permissions' => self::validated($finance)],
        ];
    }

    /** @param list<string> $permissions @return list<string> */
    private static function validated(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        $unknown = array_diff($permissions, TenantPermission::keys());
        if ($unknown !== []) {
            throw new InvalidArgumentException('Permissões desconhecidas: '.implode(', ', $unknown));
        }

        foreach (TenantPermission::catalog() as $entry) {
            if (in_array($entry['key'], $permissions, true)
                && array_diff($entry['requires'], $permissions) !== []) {
                throw new InvalidArgumentException('Dependências ausentes para '.$entry['key']);
            }
        }

        sort($permissions);

        return $permissions;
    }
}
