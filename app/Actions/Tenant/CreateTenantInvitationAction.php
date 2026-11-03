<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenantInvitationAction
{
    /**
     * @return array{invitation: TenantInvitation, token: string}
     */
    public function handle(
        User $owner,
        Tenant $tenant,
        string $email,
        TenantUserRole $role,
    ): array {
        $email = Str::lower(trim($email));

        if ($role === TenantUserRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'N�o � permitido convidar outro propriet�rio.',
            ]);
        }

        return DB::transaction(function () use (
            $owner,
            $tenant,
            $email,
            $role,
        ): array {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)->authorize('addMember', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if ($subscription === null || ! $subscription->allowsOperationalWrites()) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura n�o permite convidar membros.',
                ]);
            }

            $alreadyBelongsToTenant = $tenant->users()
                ->where('users.email', $email)
                ->exists();

            if ($alreadyBelongsToTenant) {
                throw ValidationException::withMessages([
                    'email' => 'Este usu�rio j� possui v�nculo com a empresa.',
                ]);
            }

            $pendingInvitation = $tenant->invitations()
                ->where('email', $email)
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();

            if ($pendingInvitation) {
                throw ValidationException::withMessages([
                    'email' => 'J� existe um convite pendente para este e-mail.',
                ]);
            }

            $membersCount = $tenant->users()->count();

            $reservedSeatsCount = $tenant->invitations()
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->count();

            if ($membersCount + $reservedSeatsCount >= $subscription->user_limit) {
                throw ValidationException::withMessages([
                    'user_limit' => 'O limite de usu�rios da assinatura foi atingido.',
                ]);
            }

            $token = Str::random(64);

            $invitation = new TenantInvitation([
                'email' => $email,
                'role' => $role,
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
