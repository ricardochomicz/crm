<?php

namespace App\Actions\Team;

use App\Enums\TenantUserStatus;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateTeamAction
{
    /**
     * @param  array{name: string, supervisor_membership_id?: string|null, tenant_unit_id?: string}  $data
     */
    public function handle(User $owner, Tenant $tenant, array $data): Team
    {
        return DB::transaction(function () use ($owner, $tenant, $data): Team {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)->authorize('createTeam', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites()
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite criar equipes.',
                ]);
            }

            $supervisor = isset($data['supervisor_membership_id']) ? TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($data['supervisor_membership_id'])
                ->where('status', TenantUserStatus::Active->value)
                ->lockForUpdate()
                ->first() : null;

            if (isset($data['supervisor_membership_id']) && $supervisor === null) {
                throw ValidationException::withMessages([
                    'supervisor_membership_id' => 'Selecione um membro ativo deste tenant.',
                ]);
            }

            if ($supervisor !== null) {
                if ($supervisor->tenantRole?->code !== 'SUPERVISOR') {
                    throw ValidationException::withMessages(['supervisor_membership_id' => 'Selecione um membro com o cargo Supervisor de Equipe.']);
                }
                if ($tenant->teams()->where('supervisor_membership_id', $supervisor->getKey())->exists()) {
                    throw ValidationException::withMessages(['supervisor_membership_id' => 'Este supervisor já está vinculado a outra equipe.']);
                }
            }

            $headquarters = isset($data['tenant_unit_id'])
                ? $tenant->units()->whereKey($data['tenant_unit_id'])->firstOrFail()
                : $tenant->units()->where('is_headquarters', true)->first();
            if ($supervisor !== null) {
                $headquarters?->members()->syncWithoutDetaching([$supervisor->id => ['tenant_id' => $tenant->id]]);
            }

            return $tenant->teams()->create([
                'name' => $data['name'],
                'tenant_unit_id' => $headquarters?->id,
                'supervisor_membership_id' => $supervisor?->getKey(),
            ]);
        }, attempts: 5);
    }
}
