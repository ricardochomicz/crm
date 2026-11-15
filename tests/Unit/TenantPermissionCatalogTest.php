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
        self::assertSameCanonicalizing(TenantPermission::keys(), $keys);
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

    public function test_consultant_does_not_default_to_company_commission_tables(): void
    {
        $permissions = DefaultTenantRolePermissions::all()['CONSULTANT']['permissions'];
        self::assertNotContains('commissions.view', $permissions);
        self::assertNotContains('commissions.manage', $permissions);
        self::assertContains('commercial-goals.view', $permissions);
    }
}
