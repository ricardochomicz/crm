<?php

namespace App\Http\Controllers\Client;

use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpportunityOwnershipTransferHistoryController extends Controller
{
    public function __invoke(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $actor = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'ACTIVE')
            ->firstOrFail();

        abort_unless(in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);

        $filters = $request->validate([
            'client_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DB::table('opportunity_ownership_transfers as history')
            ->join('opportunities as opportunity', 'opportunity.id', '=', 'history.opportunity_id')
            ->join('clients as client', 'client.id', '=', 'history.client_id')
            ->join('tenant_users as old_member', 'old_member.id', '=', 'history.from_tenant_user_id')
            ->join('users as old_user', 'old_user.id', '=', 'old_member.user_id')
            ->join('tenant_users as new_member', 'new_member.id', '=', 'history.to_tenant_user_id')
            ->join('users as new_user', 'new_user.id', '=', 'new_member.user_id')
            ->join('users as actor_user', 'actor_user.id', '=', 'history.actor_user_id')
            ->where('history.tenant_id', $tenant->id)
            ->where('opportunity.tenant_id', $tenant->id)
            ->where('client.tenant_id', $tenant->id)
            ->select([
                'history.id', 'history.client_id', 'history.opportunity_id',
                'history.source_type', 'history.source_id', 'history.reason', 'history.created_at',
                'opportunity.title as opportunity_title', 'client.company_name as client_name',
                'old_user.name as from_name', 'new_user.name as to_name',
                'actor_user.name as actor_name',
            ]);

        if (isset($filters['client_id'])) {
            $query->where('history.client_id', $filters['client_id']);
        }

        $page = $query->orderByDesc('history.created_at')
            ->orderByDesc('history.id')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
