<?php

namespace App\Http\Controllers\Operator;

use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ListTenantOperatorsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $membership = $tenant->users()->where('users.id', $request->user()->id)->first();
        $isOwner = $membership && $membership->membership->role === TenantUserRole::Owner;
        $canViewCommissions = $request->user()->can('viewCommissions', $tenant);
        abort_unless($isOwner || $request->user()->can('viewProducts', $tenant) || $canViewCommissions, 403);

        $selected = $tenant->operators()->pluck('operators.id')->all();

        return response()->json([
            'data' => Operator::query()->where(function ($query) use ($selected): void {
                $query->where('is_active', true)->orWhereIn('id', $selected);
            })->orderBy('name')->get(['id', 'name', 'slug', 'is_active'])->map(fn (Operator $operator) => [
                'id' => $operator->id,
                'name' => $operator->name,
                'slug' => $operator->slug,
                'is_active' => $operator->is_active,
                'selected' => in_array($operator->id, $selected, true),
            ]),
            'meta' => ['can_manage' => $isOwner],
        ]);
    }
}
