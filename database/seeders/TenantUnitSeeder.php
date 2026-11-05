<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantUnitSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            $tenant->units()->firstOrCreate(['is_headquarters' => true], [
                'name' => 'Matriz', 'email' => $tenant->email, 'phone' => $tenant->phone,
            ]);
        });
    }
}
