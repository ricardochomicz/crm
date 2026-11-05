<?php

namespace Database\Seeders;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TeamDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Este seeder é exclusivo para ambientes de desenvolvimento.');
        }

        $tenantId = env('DEMO_TENANT_ID');
        if (! $tenantId) {
            throw new RuntimeException('Informe DEMO_TENANT_ID com o UUID da empresa antes de executar o seeder.');
        }

        $tenant = Tenant::query()->findOrFail($tenantId);

        DB::transaction(function () use ($tenant): void {
            for ($number = 1; $number <= 4; $number++) {
                $email = "equipe.teste{$number}.{$tenant->id}@example.test";
                $user = User::query()->firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => fake('pt_BR')->name(),
                        'password' => Str::random(48),
                        'email_verified_at' => now(),
                    ],
                );

                TenantUser::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    [
                        'role' => TenantUserRole::Consultant->value,
                        'status' => TenantUserStatus::Active->value,
                        'joined_at' => now(),
                    ],
                );
            }
        });

        $this->command?->info('Quatro usuários de demonstração estão disponíveis na empresa '.$tenant->name.'.');
    }
}
