<?php

namespace Tests\Unit;

use App\Enums\TenantPermission;
use App\Support\Permissions\DefaultTenantRolePermissions;
use PHPUnit\Framework\TestCase;

class TenantPermissionCatalogTest extends TestCase
{
    public function test_every_permission_is_listed_once_in_role_editor_catalog(): void
    {
        $keys = array_column(TenantPermission::catalog(), 'key');
        self::assertCount(count(TenantPermission::keys()), $keys);
        $expected = TenantPermission::keys();
        sort($expected);
        sort($keys);
        self::assertSame($expected, $keys);
        self::assertCount(count(array_unique($keys)), $keys);
    }

    public function test_all_catalog_dependencies_exist(): void
    {
        $keys = TenantPermission::keys();
        foreach (TenantPermission::catalog() as $entry) {
            foreach ($entry['requires'] as $required) {
                self::assertContains($required, $keys, $entry['key']);
                self::assertNotSame($required, $entry['key']);
            }
        }
    }

    public function test_all_default_roles_have_valid_permission_dependencies(): void
    {
        foreach (DefaultTenantRolePermissions::all() as $code => $role) {
            foreach (TenantPermission::catalog() as $entry) {
                if (! in_array($entry['key'], $role['permissions'], true)) {
                    continue;
                }
                self::assertSame([], array_diff($entry['requires'], $role['permissions']), $code.' -> '.$entry['key']);
            }
        }
    }

    public function test_manager_has_both_company_commission_permissions(): void
    {
        $permissions = DefaultTenantRolePermissions::all()['MANAGER']['permissions'];
        self::assertContains('commissions.view', $permissions);
        self::assertContains('commissions.manage', $permissions);
    }

    public function test_consultant_does_not_default_to_company_commission_tables(): void
    {
        $permissions = DefaultTenantRolePermissions::all()['CONSULTANT']['permissions'];
        self::assertNotContains('commissions.view', $permissions);
        self::assertNotContains('commissions.manage', $permissions);
        self::assertContains('commercial-goals.view', $permissions);
    }
}
