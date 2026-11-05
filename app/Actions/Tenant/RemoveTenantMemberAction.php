<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RemoveTenantMemberAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function handle(User $actor, Tenant $tenant, string $membershipId): void
    {
        DB::transaction(function () use ($actor, $tenant, $membershipId): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            Gate::forUser($actor)->authorize('removeMember', $tenant);

            $subscription = $tenant->subscription()->lockForUpdate()->first();
            if ($subscription === null || ! $subscription->allowsOperationalWrites()) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite desvincular membros neste momento.',
                ]);
            }

            $membership = TenantUser::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($membershipId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->permissions->canManageMember($actor, $tenant, $membership), 403, 'Você não pode desvincular este membro.');

            if ($membership->role === TenantUserRole::Owner) {
                throw ValidationException::withMessages([
                    'member' => 'O proprietário não pode ser desvinculado.',
                ]);
            }

            if ($membership->team_id !== null || $membership->supervisedTeams()->exists()) {
                throw ValidationException::withMessages([
                    'member' => 'Remova a pessoa de sua equipe e substitua sua supervisão antes de desvinculá-la.',
                ]);
            }

            DB::table('tenant_member_departures')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->getKey(),
                'membership_id' => $membership->getKey(),
                'user_id' => $membership->user_id,
                'role' => $membership->role->value,
                'actor_user_id' => $actor->getKey(),
                'created_at' => now(),
            ]);

            $membership->delete();
        }, attempts: 5);
    }
}
