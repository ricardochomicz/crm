<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantUnit> */
class TenantUnitFactory extends Factory
{
    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->city(), 'is_headquarters' => null];
    }
}
