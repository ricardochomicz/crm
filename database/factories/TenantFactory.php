<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'type' => TenantType::Individual,
            'document' => '52998224725',
            'trade_name' => null,
            'email' => fake()->safeEmail(),
            'phone' => null,
            'status' => TenantStatus::Active,
            'timezone' => 'America/Sao_Paulo',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes): array => [
            'status' => TenantStatus::Suspended,
        ]);
    }
}
