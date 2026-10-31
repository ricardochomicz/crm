<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateTenantMemberStatusAction
{
    public function handle(
        User $owner,
        Tenant $tenant,
        string $membershipId,
        TenantUserStatus $status,
    ): void {
        if (! in_array($status, [
            TenantUserStatus::Active,
            TenantUserStatus::Suspended,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status de vínculo não permitido.',
            ]);
        }

        DB::transaction(function () use (
            $owner,
            $tenant,
            $membershipId,
            $status,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)
                ->authorize('updateMemberStatus', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites()
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite alterar o status de membros.',
                ]);
            }

            $membership = TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->role === TenantUserRole::Owner) {
                throw ValidationException::withMessages([
                    'status' => 'O vínculo do proprietário não pode ser alterado por esta operação.',
                ]);
            }

            if ($membership->status === $status) {
                return;
            }

            if (
                $status === TenantUserStatus::Suspended
                && $membership->supervisedTeams()->exists()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Substitua o supervisor nas equipes antes de suspender este membro.',
                ]);
            }

            $membership->status = $status;
            $membership->save();
        }, attempts: 5);
    }
}
