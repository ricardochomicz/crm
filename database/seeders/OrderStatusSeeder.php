<?php

namespace Database\Seeders;

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

        foreach ($names as $name) {
            $exists = DB::table('order_statuses')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('order_statuses')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'is_initial' => $name === 'TRIAGEM',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('order_statuses')->where('name', '!=', 'TRIAGEM')->update(['is_initial' => false]);
        DB::table('order_statuses')->where('name', 'TRIAGEM')->update(['is_initial' => true]);
    }
}
