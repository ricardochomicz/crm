<?php

namespace App\Actions\Tenant;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChangeTenantStatusAction
{
    public function handle(
        User $administrator,
        Tenant $tenant,
        TenantStatus $newStatus,
        string $reason,
    ): void {
        Gate::forUser($administrator)
            ->authorize('access-platform-admin');

        if (! in_array($newStatus, [
            TenantStatus::Active,
            TenantStatus::Suspended,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Esta operação permite apenas suspender ou reativar.',
            ]);
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Informe um motivo entre 10 e 2000 caracteres.',
            ]);
        }

        DB::transaction(function () use (
            $administrator,
            $tenant,
            $newStatus,
            $reason,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            $previousStatus = $tenant->status;

            if ($previousStatus === TenantStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'Empresas canceladas exigem um fluxo específico de recuperação.',
                ]);
            }

            if ($previousStatus === $newStatus) {
                return;
            }

            $tenant->status = $newStatus;
            $tenant->save();

            DB::table('tenant_status_changes')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->getKey(),
                'actor_user_id' => $administrator->getKey(),
                'previous_status' => $previousStatus->value,
                'new_status' => $newStatus->value,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        }, attempts: 5);
    }
}
