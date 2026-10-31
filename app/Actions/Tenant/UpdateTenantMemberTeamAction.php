<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateTenantMemberTeamAction
{
    public function handle(
        User $owner,
        Tenant $tenant,
        string $membershipId,
        ?string $teamId,
        string $reason,
    ): void {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Informe um motivo entre 10 e 2000 caracteres.',
            ]);
        }

        DB::transaction(function () use (
            $owner,
            $tenant,
            $membershipId,
            $teamId,
            $reason,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)
                ->authorize('updateMemberTeam', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites()
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite movimentar membros entre equipes.',
                ]);
            }

            $membership = TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();

            $newTeamId = null;

            if ($teamId !== null) {
                if ($membership->status !== TenantUserStatus::Active) {
                    throw ValidationException::withMessages([
                        'team_id' => 'Somente membros ativos podem ser associados a uma equipe.',
                    ]);
                }

                $team = $tenant->teams()
                    ->whereKey($teamId)
                    ->lockForUpdate()
                    ->first();

                if ($team === null) {
                    throw ValidationException::withMessages([
                        'team_id' => 'Selecione uma equipe deste tenant.',
                    ]);
                }

                $newTeamId = $team->getKey();
            }

            $previousTeamId = $membership->team_id;

            if ($previousTeamId === $newTeamId) {
                return;
            }

            $membership->team_id = $newTeamId;
            $membership->save();

            DB::table('tenant_member_team_changes')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->getKey(),
                'membership_id' => $membership->getKey(),
                'previous_team_id' => $previousTeamId,
                'new_team_id' => $newTeamId,
                'actor_user_id' => $owner->getKey(),
                'reason' => $reason,
                'created_at' => now(),
            ]);
        }, attempts: 5);
    }
}
