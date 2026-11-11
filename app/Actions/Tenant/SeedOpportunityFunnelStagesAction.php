<?php

namespace App\Actions\Tenant;

use App\Models\OpportunityFunnelStage;
use App\Models\Tenant;

class SeedOpportunityFunnelStagesAction
{
    private const DEFAULT_STAGES = ['Prospecção', 'Negociação', 'Proposta', 'Fechamento'];

    public function execute(Tenant $tenant): void
    {
        // Preserve custom funnels and existing stage identifiers.
        if (OpportunityFunnelStage::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (self::DEFAULT_STAGES as $index => $name) {
            OpportunityFunnelStage::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'position' => ($index + 1) * 10,
                'is_active' => true,
            ]);
        }
    }
}
