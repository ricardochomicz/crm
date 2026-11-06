<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenantInvitationAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /**
     * @return array{invitation: TenantInvitation, token: string}
     */
    public function handle(
        User $owner,
        Tenant $tenant,
        string $email,
        TenantUserRole $role,
        ?string $tenantRoleId = null,
    ): array {
        $email = Str::lower(trim($email));

        if ($role === TenantUserRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Não é permitido convidar outro proprietário.',
            ]);
        }

        return DB::transaction(function () use (
            $owner,
            $tenant,
            $email,
            $role,
            $tenantRoleId,
        ): array {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)->authorize('createInvitation', $tenant);

            abort_unless($this->permissions->canAssign($owner, $tenant, $role), 403, 'Você não pode atribuir este cargo.');
            if ($tenantRoleId !== null) {
                $selectedRole = TenantRole::where('tenant_id', $tenant->id)->where('is_active', true)->findOrFail($tenantRoleId);
                abort_unless($selectedRole->code !== 'OWNER' && $selectedRole->legacy_role === $role->value, 422, 'Cargo incompatível com o papel informado.');
                abort_unless($this->permissions->membership($owner, $tenant)?->role === TenantUserRole::Owner, 403, 'Somente o proprietário pode atribuir cargos personalizados.');
            }

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if ($subscription === null || ! $subscription->allowsOperationalWrites()) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite convidar membros.',
                ]);
            }

            $alreadyBelongsToTenant = $tenant->users()
                ->where('users.email', $email)
                ->exists();

            if ($alreadyBelongsToTenant) {
                throw ValidationException::withMessages([
                    'email' => 'Este usuário já possui vínculo com a empresa.',
                ]);
            }

            $pendingInvitation = $tenant->invitations()
                ->where('email', $email)
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingInvitation) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe um convite pendente para este e-mail.',
                ]);
            }

            $membersCount = $tenant->users()->count();

            $reservedSeatsCount = $tenant->invitations()
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->count();

            if ($membersCount + $reservedSeatsCount >= $subscription->user_limit) {
                throw ValidationException::withMessages([
                    'user_limit' => 'O limite de usuários da assinatura foi atingido.',
                ]);
            }

            $resolvedRoleId = $tenantRoleId ?? TenantRole::where('tenant_id', $tenant->id)->where('code', match ($role) {
                TenantUserRole::Manager => 'MANAGER',
                TenantUserRole::Consultant => 'TELEMARKETING',
                TenantUserRole::Assistant => 'BACKOFFICE',
                default => 'OWNER',
            })->value('id');

            $token = Str::random(64);

            $invitation = new TenantInvitation([
                'email' => $email,
                'role' => $role,
                'tenant_role_id' => $resolvedRoleId,
                'status' => TenantInvitationStatus::Pending,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);

            $invitation->invited_by_user_id = $owner->getKey();

            $tenant->invitations()->save($invitation);

            return [
                'invitation' => $invitation,
                'token' => $token,
            ];
        }, attempts: 5);
    }
}
