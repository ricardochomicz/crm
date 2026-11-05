<?php

namespace Database\Seeders;

use App\Models\Operator;
use Illuminate\Database\Seeder;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'vivo' => 'Vivo',
            'claro' => 'Claro',
            'tim' => 'TIM',
            'algar-telecom' => 'Algar Telecom',
            'brisanet' => 'Brisanet',
            'unifique' => 'Unifique',
        ] as $slug => $name) {
            Operator::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }
    }
}
