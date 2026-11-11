<?php

namespace Database\Seeders;

use App\Actions\Tenant\SeedOpportunityFunnelStagesAction;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class OpportunityFunnelStagesSeeder extends Seeder
{
    public function run(): void
    {
        $count = 0;
        $action = app(SeedOpportunityFunnelStagesAction::class);
        Tenant::query()->chunkById(100, function ($tenants) use ($action, &$count): void {
            foreach ($tenants as $tenant) {
                $action->execute($tenant);
                $count++;
            }
        });
        $this->command?->info("Funis verificados para {$count} empresa(s).");
    }
}
