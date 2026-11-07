<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Consolidate identical tenant statuses without breaking existing order references.
        $canonical = [];
        foreach (DB::table('order_statuses')->orderBy('id')->get() as $status) {
            $name = mb_strtoupper(trim($status->name));
            $name = match ($name) {
                'CONCLUÍDO' => 'CONCLUIDO',
                'ANÁLISE DE CRÉDITO' => 'ANALISE DE CRÉDITO',
                'LOGÍSTICA' => 'LOGISTICA',
                'PARCIALMENTE ATIVADO' => 'PARCIALMENTE ATIVADO',
                default => $name,
            };
            if (isset($canonical[$name])) {
                DB::table('commercial_orders')->where('order_status_id', $status->id)
                    ->update(['order_status_id' => $canonical[$name]]);
                DB::table('order_statuses')->where('id', $status->id)->delete();
            } else {
                $canonical[$name] = $status->id;
                DB::table('order_statuses')->where('id', $status->id)
                    ->update(['name' => $name, 'is_initial' => $name === 'TRIAGEM']);
            }
        }

        Schema::table('order_statuses', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'name']);
            $table->dropColumn('tenant_id');
        });
        Schema::table('order_statuses', fn (Blueprint $table) => $table->unique('name'));
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->foreignUuid('tenant_id')->nullable();
            $table->index(['tenant_id', 'name']);
        });
        // Existing global statuses cannot be reassigned to individual tenants.
    }
};
