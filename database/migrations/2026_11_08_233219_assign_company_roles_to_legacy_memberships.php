<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $codes = ['OWNER' => 'OWNER', 'MANAGER' => 'MANAGER', 'CONSULTANT' => 'TELEMARKETING', 'ASSISTANT' => 'BACKOFFICE'];

        foreach ($codes as $legacyRole => $code) {
            DB::table('tenant_roles')->where('code', $code)->where('legacy_role', $legacyRole)
                ->orderBy('id')->chunkById(100, function (Collection $roles) use ($legacyRole): void {
                    foreach ($roles as $role) {
                        foreach (['tenant_users', 'tenant_invitations'] as $table) {
                            DB::table($table)->where('tenant_id', $role->tenant_id)
                                ->where('role', $legacyRole)->whereNull('tenant_role_id')
                                ->update(['tenant_role_id' => $role->id]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Keep resolved assignments: reverting would restore obsolete permissions.
    }
};
