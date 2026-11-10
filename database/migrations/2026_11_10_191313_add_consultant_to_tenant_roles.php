<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->select('id')->lazyById(100) as $tenant) {
            if (DB::table('tenant_roles')->where('tenant_id', $tenant->id)->where('code', 'CONSULTANT')->exists()) {
                continue;
            }

            $permissions = DB::table('tenant_role_permissions')->where('tenant_id', $tenant->id)
                ->where('role', 'CONSULTANT')->value('permissions') ?? '[]';

            DB::table('tenant_roles')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'code' => 'CONSULTANT',
                'name' => 'Consultor',
                'legacy_role' => 'CONSULTANT',
                'permissions' => $permissions,
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve roles that may already have members, invitations and customized permissions.
    }
};
