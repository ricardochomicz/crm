<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\ListTeamsRequest;
use App\Models\Team;
use App\Models\Tenant;

class ListTeamsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListTeamsRequest $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $filters = $request->validated();

        $query = $tenant->teams()
            ->with('supervisorMembership.user')
            ->withCount('members');

        if (isset($filters['supervisor_membership_id'])) {
            $query->where(
                'supervisor_membership_id',
                $filters['supervisor_membership_id'],
            );
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters)
            ->through(function (Team $team): array {
                $supervisor = $team->supervisorMembership;

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members_count' => $team->members_count,
                    'supervisor' => $supervisor === null ? null : [
                        'membership_id' => $supervisor->id,
                        'user_id' => $supervisor->user_id,
                        'name' => $supervisor->user?->name,
                        'status' => $supervisor->status->value,
                    ],
                ];
            })
            ->toArray();
    }
}