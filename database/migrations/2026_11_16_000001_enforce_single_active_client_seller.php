<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        // Never silently discard conflicting assignments or their audit history.
        $duplicate = DB::table('client_sellers')
            ->select('tenant_id', 'client_id')
            ->whereNull('ended_at')
            ->groupBy('tenant_id', 'client_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Existem clientes com mais de um responsável ativo. Regularize os vínculos antes de aplicar a migração.'
            );
        }

        match (DB::getDriverName()) {
            'sqlite', 'pgsql' => DB::statement(
                'CREATE UNIQUE INDEX client_sellers_one_active_idx ON client_sellers (tenant_id, client_id) WHERE ended_at IS NULL'
            ),
            'mysql' => DB::statement(
                'ALTER TABLE client_sellers ADD COLUMN active_client_id CHAR(36) GENERATED ALWAYS AS (CASE WHEN ended_at IS NULL THEN client_id ELSE NULL END) STORED, ADD UNIQUE INDEX client_sellers_one_active_idx (tenant_id, active_client_id)'
            ),
            default => throw new RuntimeException('Driver não suportado para unicidade da carteira ativa.'),
        };
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE client_sellers DROP INDEX client_sellers_one_active_idx, DROP COLUMN active_client_id');
            return;
        }

        DB::statement('DROP INDEX IF EXISTS client_sellers_one_active_idx');
    }
};
