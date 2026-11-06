<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantInvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantInvitationsRequest;
use App\Models\Tenant;
use App\Models\TenantInvitation;

class ListTenantInvitationsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        ListTenantInvitationsRequest $request,
    ): array {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $filters = $request->validated();
        $at = now();

        $query = $tenant->invitations();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($filters['per_page'] ?? 20),
                [
                    'id',
                    'email',
                    'role',
                    'status',
                    'expires_at',
                    'accepted_at',
                    'revoked_at',
                    'created_at',
                ],
            )
            ->appends($filters)
            ->through(function (TenantInvitation $invitation) use ($at): array {
                $isPending = $invitation->status
                    === TenantInvitationStatus::Pending;

                return [
                    'id' => $invitation->getKey(),
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'tenant_role_id' => $invitation->tenant_role_id,
                    'role_name' => $invitation->tenantRole?->name,
                    'status' => $invitation->status->value,
                    'is_expired' => $isPending
                        && $invitation->expires_at->lessThanOrEqualTo($at),
                    'reserves_seat' => $isPending
                        && $invitation->expires_at->greaterThan($at),
                    'expires_at' => $invitation->expires_at
                        ->toISOString(),
                    'accepted_at' => $invitation->accepted_at
                        ?->toISOString(),
                    'revoked_at' => $invitation->revoked_at
                        ?->toISOString(),
                    'created_at' => $invitation->created_at
                        ->toISOString(),
                ];
            })
            ->toArray();
    }
}
