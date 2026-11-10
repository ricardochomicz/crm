<?php

namespace App\Http\Controllers\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpportunityHighlightController extends Controller
{
    private function context(Request $request, ResolveTenantPermissionsAction $permissions): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant && $request->user()?->can('viewOpportunities', $tenant), 403);
        $actor = $permissions->membership($request->user(), $tenant);
        abort_unless($actor, 403);

        return [$tenant, $actor];
    }

    public function index(Request $request, OpportunityAction $opportunities, ResolveTenantPermissionsAction $permissions): JsonResponse
    {
        [$tenant] = $this->context($request, $permissions);

        return response()->json(['data' => $opportunities->highlightedIds($tenant, $request->user())]);
    }

    public function update(
        Request $request,
        string $tenantId,
        string $opportunityId,
        OpportunityAction $opportunities,
        ResolveTenantPermissionsAction $permissions
    ): JsonResponse {
        [$tenant, $actor] = $this->context($request, $permissions);
        $data = $request->validate(['highlighted' => ['required', 'boolean']]);
        $opportunities->find($tenant, $request->user(), $opportunityId);

        $key = [
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $actor->id,
            'opportunity_id' => $opportunityId,
        ];

        if ($data['highlighted']) {
            DB::table('opportunity_highlights')->insertOrIgnore([
                ...$key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('opportunity_highlights')->where($key)->delete();
        }

        return response()->json(['data' => ['id' => $opportunityId, 'highlighted' => (bool) $data['highlighted']]]);
    }
}
