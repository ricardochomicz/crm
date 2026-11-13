<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\ResolveCommercialRadarMembersAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommercialRadarMembersController extends Controller
{
    public function __invoke(
        Request $request,
        string $tenantId,
        ResolveCommercialRadarMembersAction $members,
    ): JsonResponse {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant && $tenant->id === $tenantId, 403);

        $memberIds = $members->selectable($tenant, $request->user());

        $options = TenantUser::query()
            ->with('user:id,name')
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $memberIds)
            ->get(['id', 'user_id'])
            ->map(fn (TenantUser $member): array => [
                'id' => $member->id,
                'name' => $member->user?->name ?? 'Usuário',
            ])
            ->sortBy('name')
            ->values();

        return response()->json(['data' => $options]);
    }
}
