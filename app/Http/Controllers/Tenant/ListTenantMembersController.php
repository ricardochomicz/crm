<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListTenantMembersController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        Gate::forUser($request->user())
            ->authorize('viewMembers', $tenant);

        return $tenant->users()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(20, [
                'users.id',
                'users.name',
                'users.email',
            ])
            ->through(fn(User $user): array => [
                'id' => $user->membership->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->membership->role->value,
                'status' => $user->membership->status->value,
                'joined_at' => $user->membership->joined_at?->toIso8601String(),
            ])
            ->toArray();
    }
}
