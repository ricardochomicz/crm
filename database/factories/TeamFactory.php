<?php

namespace Database\Factories;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUser;
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
            'supervisor_membership_id' => function (array $attributes): string {
                $supervisor = TenantUser::query()->create([
                    'tenant_id' => $attributes['tenant_id'],
                    'user_id' => User::factory()->create()->getKey(),
                    'role' => TenantUserRole::Consultant,
                    'status' => TenantUserStatus::Active,
                    'joined_at' => now(),
                ]);

                return $supervisor->getKey();
            },
        ];
    }

    public function supervisedBy(TenantUser $supervisor): static
    {
        return $this->state(fn(array $attributes): array => [
            'tenant_id' => $supervisor->tenant_id,
            'supervisor_membership_id' => $supervisor->getKey(),
        ]);
    }
}
