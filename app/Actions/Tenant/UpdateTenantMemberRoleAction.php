<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateTenantMemberRoleAction
{
    public function handle(
        User $owner,
        Tenant $tenant,
        string $membershipId,
        TenantUserRole $role,
    ): void {
        if (! in_array($role, [
            TenantUserRole::Manager,
            TenantUserRole::Consultant,
            TenantUserRole::Assistant,
        ], true)) {
            throw ValidationException::withMessages([
                'role' => 'Este papel não pode ser atribuído por esta operação.',
            ]);
        }

        DB::transaction(function () use (
            $owner,
            $tenant,
            $membershipId,
            $role,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)
                ->authorize('updateMemberRole', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites()
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite alterar papéis.',
                ]);
            }

            $membership = TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->role === TenantUserRole::Owner) {
                throw ValidationException::withMessages([
                    'role' => 'O papel do proprietário não pode ser alterado por esta operação.',
                ]);
            }

            if ($membership->role === $role) {
                return;
            }

            $membership->role = $role;
            $membership->save();
        }, attempts: 5);
    }
}
