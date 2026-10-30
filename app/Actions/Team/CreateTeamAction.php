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
     * @param array{name: string, supervisor_membership_id: string} $data
     */
    public function handle(User $owner, Tenant $tenant, array $data): Team
    {
        return DB::transaction(function () use ($owner, $tenant, $data): Team {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)->authorize('createTeam', $tenant);

            $supervisor = TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($data['supervisor_membership_id'])
                ->where('status', TenantUserStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($supervisor === null) {
                throw ValidationException::withMessages([
                    'supervisor_membership_id' => 'Selecione um membro ativo deste tenant que ainda não pertença a uma equipe.',
                ]);
            }

            return $tenant->teams()->create([
                'name' => $data['name'],
                'supervisor_membership_id' => $supervisor->getKey()
            ]);
        }, attempts: 5);
    }
}
