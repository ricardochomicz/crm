<?php

namespace App\Actions\Team;

use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateTeamSupervisorAction
{
    public function handle(
        User $owner,
        Tenant $tenant,
        string $teamId,
        string $supervisorMembershipId,
    ): void {
        DB::transaction(function () use (
            $owner,
            $tenant,
            $teamId,
            $supervisorMembershipId,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)
                ->authorize('updateTeamSupervisor', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites()
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite substituir supervisores.',
                ]);
            }

            $team = $tenant->teams()
                ->whereKey($teamId)
                ->lockForUpdate()
                ->firstOrFail();

            $supervisor = TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($supervisorMembershipId)
                ->where('status', TenantUserStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($supervisor === null) {
                throw ValidationException::withMessages([
                    'supervisor_membership_id' => 'Selecione um membro ativo deste tenant.',
                ]);
            }

            if ($team->supervisor_membership_id === $supervisor->getKey()) {
                return;
            }

            $team->supervisor_membership_id = $supervisor->getKey();
            $team->save();
        }, attempts: 5);
    }
}
