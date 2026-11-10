<?php

namespace Database\Seeders;

use App\Actions\Tenant\SeedTenantRolesAction;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Pode ser executado novamente: cria somente cargos ausentes.
 * Empresas novas já recebem os cargos via CreateTenantAction.
 */
class TenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        $seed = app(SeedTenantRolesAction::class);

        Tenant::query()->orderBy('id')->chunk(100, function ($tenants) use ($seed): void {
            foreach ($tenants as $tenant) {
                $seed->execute($tenant);
            }
        });
    }
}
