<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Equipe ' . fake()->word(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Team $team): void {
            $supervisor = User::factory()->create();

            $team->tenant->users()->attach($supervisor->getKey(), [
                'role' => TenantUserRole::Consultant->value,
                'status' => TenantUserStatus::Active->value,
                'joined_at' => now(),
                'team_id' => $team->getKey(),
                'team_role' => TeamRole::Supervisor->value,
            ]);
        });
    }
}
