<?php

namespace Database\Seeders;

use App\Enums\OrderStatusCode;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'TRIAGEM', 'ATIVO', 'CONCLUIDO', 'ANALISE DE CRÉDITO',
            'VALIDAÇÃO', 'PARA CORREÇÃO', 'PARCIALMENTE ATIVADO',
            'LOGISTICA', 'AGUARDANDO PORTABILIDADE', 'PENDENTE ASSINATURA',
            'PENDENTE BIOMETRIA', 'PENDENTE SMS',
        ];

        $codes = [
            'TRIAGEM' => OrderStatusCode::Triage->value,
            'ATIVO' => OrderStatusCode::Active->value,
            'CONCLUIDO' => OrderStatusCode::Completed->value,
            'PARA CORREÇÃO' => OrderStatusCode::Correction->value,
            'PARCIALMENTE ATIVADO' => OrderStatusCode::PartiallyActivated->value,
        ];

        foreach ($names as $name) {
            $code = $codes[$name] ?? null;
            $status = DB::table('order_statuses')
                ->where(function (Builder $query) use ($name, $code): void {
                    $query->where('name', $name);
                    if ($code !== null) {
                        $query->orWhere('code', $code);
                    }
                })->first();

            if ($status === null) {
                DB::table('order_statuses')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'code' => $code,
                    'is_initial' => $code === OrderStatusCode::Triage->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($code !== null && $status->code === null) {
                DB::table('order_statuses')->where('id', $status->id)->update(['code' => $code]);
            }
        }

        DB::table('order_statuses')->update(['is_initial' => false]);
        DB::table('order_statuses')->where('code', OrderStatusCode::Triage->value)->update(['is_initial' => true]);
    }
}
