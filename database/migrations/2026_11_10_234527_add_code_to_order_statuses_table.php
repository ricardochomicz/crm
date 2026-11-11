<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $codes = [
            'TRIAGEM' => 'TRIAGE',
            'ATIVO' => 'ACTIVE',
            'CONCLUIDO' => 'COMPLETED',
            'PARA CORRECAO' => 'CORRECTION',
            'PARCIALMENTE ATIVADO' => 'PARTIALLY_ACTIVATED',
        ];
        $assignments = [];

        foreach (DB::table('order_statuses')->get(['id', 'name']) as $status) {
            $name = Str::upper(Str::ascii(trim($status->name)));
            $code = $codes[$name] ?? null;

            if ($code === null) {
                continue;
            }

            if (isset($assignments[$code])) {
                throw new RuntimeException("Existem status duplicados para {$code}. Consolide-os antes de migrar.");
            }

            $assignments[$code] = $status->id;
        }

        Schema::table('order_statuses', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique();
        });

        foreach ($assignments as $code => $id) {
            DB::table('order_statuses')->where('id', $id)->update(['code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
