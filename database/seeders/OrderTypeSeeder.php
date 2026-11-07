<?php

namespace Database\Seeders;

use App\Models\OrderType;
use Illuminate\Database\Seeder;

class OrderTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Global catalog: existing records retain their admin-managed settings.
        $types = [["NOVO",false],["INCREMENTO",false],["RENOVAÇÃO",true],["TRANSF. TITULAR PF / PJ",true],["TRANSF. TITULAR PJ / PJ",true],["PORTADO",true],["TRANSF. TITULAR PF(pré) / PJ",true],["MIGRAÇÃO",true],["INCREMENTO PORTADO",true],["TT + RENOVAÇÃO",true],["PORTAB CRUZADA PF/ PJ",true],["PORTAB CRUZADA Pré PF/ PJ",true],["INCREM PORTAB CRUZADA PF/ PJ",true],["INCREM PORTAB CRUZADA Pré PF/ PJ",true],["INCREMENTO TRANSF PF/PJ",true],["INCREMENTO TRANSF PF (PRE) / PJ",true],["RENOVAÇÃO PARCIAL",true]];
        foreach ($types as $index => [$name, $requiresNumber]) {
            OrderType::query()->firstOrCreate(
                ['name' => $name],
                ['requires_number' => $requiresNumber, 'is_active' => true, 'position' => ($index + 1) * 10],
            );
        }
    }
}
