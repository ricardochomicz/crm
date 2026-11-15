<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve custom roles and administrator edits. Only upgrade the
        // standard system roles that have not already received this permission.
        DB::table('tenant_roles')
            ->where('is_system', true)
            ->whereIn('code', ['CONSULTANT', 'MANAGER', 'SUPERVISOR', 'ADMINISTRATIVE', 'TELEMARKETING', 'BACKOFFICE', 'FINANCE'])
            ->orderBy('id')
            ->chunk(100, function ($roles): void {
                foreach ($roles as $role) {
                    $permissions = json_decode($role->permissions ?? '[]', true, flags: JSON_THROW_ON_ERROR);
                    if (in_array('my-commissions.view', $permissions, true)) {
                        continue;
                    }
                    $permissions[] = 'my-commissions.view';
                    sort($permissions);
                    DB::table('tenant_roles')->where('id', $role->id)->update([
                        'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                        'version' => $role->version + 1,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Do not revoke a permission that administrators may have intentionally
        // granted after this migration.
    }
};
