<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $names = ['Prospecção', 'Negociação', 'Proposta', 'Fechamento'];
        foreach (DB::table('tenants')->select('id')->cursor() as $tenant) {
            $exists = DB::table('opportunity_funnel_stages')->where('tenant_id', $tenant->id)->exists();
            if ($exists) continue;
            foreach ($names as $index => $name) {
                DB::table('opportunity_funnel_stages')->insert([
                    'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id,
                    'name' => $name, 'position' => ($index + 1) * 10,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Do not remove stages that may already have opportunities linked.
    }
};
