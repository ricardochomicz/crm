<?php

namespace App\Actions\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevokeTenantInvitationAction
{
    public function handle(
        User $actor,
        Tenant $tenant,
        string $invitationId,
    ): void {
        DB::transaction(function () use (
            $actor,
            $tenant,
            $invitationId,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($actor)
                ->authorize('revokeInvitation', $tenant);

            $tenant->subscription()
                ->lockForUpdate()
                ->first();

            $invitation = $tenant->invitations()
                ->whereKey($invitationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->status === TenantInvitationStatus::Revoked) {
                return;
            }

            if ($invitation->status === TenantInvitationStatus::Accepted) {
                throw ValidationException::withMessages([
                    'invitation' => 'Um convite aceito não pode ser revogado. '
                        . 'Gerencie o vínculo pela área de membros.',
                ]);
            }

            $invitation->status = TenantInvitationStatus::Revoked;
            $invitation->revoked_at = now();
            $invitation->save();
        }, attempts: 5);
    }
}
