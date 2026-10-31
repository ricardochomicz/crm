<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\ListTeamMembersRequest;
use App\Models\Tenant;
use App\Models\TenantUser;

class ListTeamMembersController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListTeamMembersRequest $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $team = $tenant->teams()
            ->whereKey((string) $request->route('teamId'))
            ->firstOrFail();

        $filters = $request->validated();

        $query = $team->members()
            ->where('tenant_id', $tenant->getKey())
            ->with('user:id,name,email');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderBy('joined_at')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters)
            ->through(fn(TenantUser $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'role' => $membership->role->value,
                'status' => $membership->status->value,
                'team_id' => $membership->team_id,
                'joined_at' => $membership->joined_at?->toIso8601String(),
            ])
            ->toArray();
    }
}
