<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\RedistributeClientPortfolioAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientPortfolioRedistributionController extends Controller
{
    public function index(Request $request, RedistributeClientPortfolioAction $action): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $action->authorize($tenant, $request->user()->id);

        return response()->json(['data' => ['members' => $action->overview($tenant)]]);
    }

    public function clients(
        Request $request,
        string $tenantId,
        string $sourceId,
        RedistributeClientPortfolioAction $action
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $action->authorize($tenant, $request->user()->id);

        return response()->json(['data' => ['clients' => $action->clients($tenant, $sourceId)]]);
    }

    public function store(Request $request, RedistributeClientPortfolioAction $action): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $action->authorize($tenant, $request->user()->id);

        $data = $request->validate([
            'source_membership_id' => ['required', 'uuid'],
            'client_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'client_ids.*' => ['required', 'uuid', 'distinct'],
            'mode' => ['required', Rule::in(['single', 'equal', 'manual'])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'destination_ids' => ['required_unless:mode,manual', 'array', 'max:200'],
            'destination_ids.*' => ['uuid', 'distinct'],
            'assignments' => ['required_if:mode,manual', 'array'],
            'assignments.*' => ['required', 'uuid'],
        ]);

        return response()->json([
            'data' => $action->execute($tenant, $request->user()->id, $data),
        ], 201);
    }
}
