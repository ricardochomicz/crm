<?php

namespace App\Actions\Commission;

use Illuminate\Support\Facades\DB;

class ManageCommissionSettingsAction
{
    public function show(string $tenantId): array
    {
        return [
            'default_deduction_percentage' => DB::table('tenant_commission_settings')
                ->where('tenant_id', $tenantId)
                ->value('default_deduction_percentage') ?? '0.00',
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(string $tenantId, array $data): array
    {
        DB::table('tenant_commission_settings')->updateOrInsert(
            ['tenant_id' => $tenantId],
            ['default_deduction_percentage' => $data['default_deduction_percentage']]
        );

        return $data;
    }
}
