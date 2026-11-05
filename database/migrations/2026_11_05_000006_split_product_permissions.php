<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('tenant_role_permissions')->get() as $row) {
            $permissions = json_decode($row->permissions, true, flags: JSON_THROW_ON_ERROR);
            if (!in_array('products.manage', $permissions, true)) {
                continue;
            }
            $permissions = array_values(array_unique(array_merge(
                array_diff($permissions, ['products.manage']),
                ['products.view', 'products.create', 'products.update', 'products.promotions'],
            )));
            DB::table('tenant_role_permissions')
                ->where('tenant_id', $row->tenant_id)
                ->where('role', $row->role)
                ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'version' => $row->version + 1]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('tenant_role_permissions')->get() as $row) {
            $permissions = json_decode($row->permissions, true, flags: JSON_THROW_ON_ERROR);
            if (!in_array('products.create', $permissions, true) || !in_array('products.update', $permissions, true) || !in_array('products.promotions', $permissions, true)) {
                continue;
            }
            $permissions = array_values(array_unique(array_merge(
                array_diff($permissions, ['products.create', 'products.update', 'products.promotions']),
                ['products.manage'],
            )));
            DB::table('tenant_role_permissions')->where('tenant_id', $row->tenant_id)->where('role', $row->role)
                ->update(['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR), 'version' => $row->version + 1]);
        }
    }
};
