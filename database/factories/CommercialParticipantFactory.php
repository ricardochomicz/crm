<?php

namespace Database\Factories;

use App\Models\CommercialParticipant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommercialParticipant> */
class CommercialParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'role' => 'PARTNER',
            'person_type' => 'PF',
            'name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
