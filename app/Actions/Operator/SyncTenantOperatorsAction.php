<?php

namespace App\Actions\Operator;

use App\Enums\TenantUserRole;
use App\Models\Operator;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncTenantOperatorsAction
{
    /** @param array<int, int> $operatorIds */
    public function execute(string $tenantId, int $userId, array $operatorIds): void
    {
        DB::transaction(function () use ($tenantId, $userId, $operatorIds): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenantId);
            $membership = $tenant->users()->where('users.id', $userId)->first();

            abort_unless(
                $membership && $membership->membership->role === TenantUserRole::Owner,
                403
            );

            $selectedInactive = $tenant->operators()
                ->where('is_active', false)
                ->pluck('operators.id')
                ->all();

            $invalid = Operator::query()
                ->whereIn('id', $operatorIds)
                ->where('is_active', false)
                ->whereNotIn('id', $selectedInactive)
                ->exists();

            if ($invalid) {
                throw ValidationException::withMessages([
                    'operator_ids' => 'Não é possível adicionar uma operadora inativa.',
                ]);
            }

            $tenant->operators()->sync($operatorIds);
        });
    }
}
