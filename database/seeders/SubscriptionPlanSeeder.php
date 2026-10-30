<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'individual',
                'name' => 'Individual',
                'description' => 'Para consultores que trabalham sozinhos.',
                'monthly_price' => '59.00',
                'user_limit' => 1,
                'storage_limit_mb' => 1024,
            ],
            [
                'code' => 'equipe',
                'name' => 'Equipe',
                'description' => 'Para empresas com até 5 usuários.',
                'monthly_price' => '199.00',
                'user_limit' => 5,
                'storage_limit_mb' => 5120,
            ],
            [
                'code' => 'empresa',
                'name' => 'Empresa',
                'description' => 'Para empresas com até 12 usuários.',
                'monthly_price' => '449.00',
                'user_limit' => 12,
                'storage_limit_mb' => 20480,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->firstOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'monthly_price' => $plan['monthly_price'],
                    'currency' => 'BRL',
                    'user_limit' => $plan['user_limit'],
                    'storage_limit_mb' => $plan['storage_limit_mb'],
                    'is_active' => true,
                ],
            );
        }
    }
}
