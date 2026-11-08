<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResendTenantInvitationAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    /**
     * @return array{invitation: TenantInvitation, token: string}
     */
    public function handle(
        User $actor,
        Tenant $tenant,
        string $invitationId,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $tenant,
            $invitationId,
        ): array {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($actor)
                ->authorize('resendInvitation', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            $invitation = $tenant->invitations()
                ->whereKey($invitationId)
                ->lockForUpdate()
                ->firstOrFail();

            $selectedRole = $this->permissions->resolveAssignableRole($actor, $tenant, $invitation->role, $invitation->tenant_role_id);
            $invitation->tenant_role_id = $selectedRole->id;

            $at = now();

            if ($invitation->status !== TenantInvitationStatus::Pending) {
                throw ValidationException::withMessages([
                    'invitation' => 'Somente convites pendentes podem ser reenviados.',
                ]);
            }

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites($at)
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite reenviar convites.',
                ]);
            }

            if ($invitation->role === TenantUserRole::Owner) {
                throw ValidationException::withMessages([
                    'role' => 'Não é permitido convidar outro proprietário.',
                ]);
            }

            $alreadyBelongsToTenant = $tenant->users()
                ->where('users.email', $invitation->email)
                ->exists();

            if ($alreadyBelongsToTenant) {
                throw ValidationException::withMessages([
                    'email' => 'Este usuário já possui vínculo com a empresa.',
                ]);
            }

            $pendingInvitation = $tenant->invitations()
                ->where('id', '!=', $invitation->getKey())
                ->where('email', $invitation->email)
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', $at)
                ->exists();

            if ($pendingInvitation) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe outro convite pendente para este e-mail.',
                ]);
            }

            $membersCount = $tenant->users()->count();

            $reservedSeatsCount = $tenant->invitations()
                ->where('id', '!=', $invitation->getKey())
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', $at)
                ->count();

            if (
                $membersCount + $reservedSeatsCount
                >= $subscription->user_limit
            ) {
                throw ValidationException::withMessages([
                    'user_limit' => 'O limite de usuários da assinatura foi atingido.',
                ]);
            }

            $token = Str::random(64);

            $invitation->token_hash = hash('sha256', $token);
            $invitation->expires_at = $at->copy()->addDays(7);
            $invitation->save();

            return [
                'invitation' => $invitation,
                'token' => $token,
            ];
        }, attempts: 5);
    }
}
