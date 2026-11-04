<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptTenantInvitationAction
{
    public function handle(User $user, string $token): TenantUser
    {
        $tokenHash = hash('sha256', $token);

        $invitationReference = TenantInvitation::query()
            ->where('token_hash', $tokenHash)
            ->first(['id', 'tenant_id']);

        if ($invitationReference === null) {
            throw ValidationException::withMessages([
                'token' => 'O convite é inválido ou não está mais disponível.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $tokenHash,
            $invitationReference,
        ): TenantUser {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->find($invitationReference->tenant_id);

            if ($tenant === null) {
                throw ValidationException::withMessages([
                    'token' => 'O convite é inválido ou não está mais disponível.',
                ]);
            }

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            $invitation = $tenant->invitations()
                ->whereKey($invitationReference->getKey())
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            $at = now();

            if (
                $invitation === null
                || $invitation->status !== TenantInvitationStatus::Pending
                || $invitation->expires_at->lessThanOrEqualTo($at)
            ) {
                throw ValidationException::withMessages([
                    'token' => 'O convite é inválido ou não está mais disponível.',
                ]);
            }

            if ($tenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages([
                    'tenant' => 'A empresa não está ativa para aceitar membros.',
                ]);
            }

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites($at)
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite aceitar membros.',
                ]);
            }

            $authenticatedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $userEmail = Str::lower(trim($authenticatedUser->email));
            $invitationEmail = Str::lower(trim($invitation->email));

            if ($userEmail !== $invitationEmail) {
                throw ValidationException::withMessages([
                    'email' => 'Entre na conta correspondente ao e-mail convidado.',
                ]);
            }

            if ($invitation->role === TenantUserRole::Owner) {
                throw ValidationException::withMessages([
                    'role' => 'Não é permitido aceitar convite como proprietário.',
                ]);
            }

            $alreadyBelongsToTenant = $tenant->users()
                ->whereKey($authenticatedUser->getKey())
                ->exists();

            if ($alreadyBelongsToTenant) {
                throw ValidationException::withMessages([
                    'email' => 'Você já possui vínculo com esta empresa.',
                ]);
            }

            $membersCount = $tenant->users()->count();

            $reservedSeatsCount = $tenant->invitations()
                ->where('status', TenantInvitationStatus::Pending->value)
                ->where('expires_at', '>', $at)
                ->count();

            if (
                $membersCount + $reservedSeatsCount
                > $subscription->user_limit
            ) {
                throw ValidationException::withMessages([
                    'user_limit' => 'O limite atual da assinatura não permite aceitar o convite.',
                ]);
            }

            $membership = new TenantUser([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $authenticatedUser->getKey(),
                'role' => $invitation->role,
                'status' => TenantUserStatus::Active,
                'joined_at' => $at,
            ]);

            $membership->save();

            $invitation->status = TenantInvitationStatus::Accepted;
            $invitation->accepted_at = $at;
            $invitation->save();

            return $membership;
        }, attempts: 5);
    }
}
