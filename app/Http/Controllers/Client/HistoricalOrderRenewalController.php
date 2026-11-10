<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\RenewHistoricalOrderAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class HistoricalOrderRenewalController extends Controller
{
    public function __invoke(Request $request, string $tenantId, string $orderId, RenewHistoricalOrderAction $action)
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant && $request->user()->can('viewClients', $tenant)
            && $request->user()->can('createOpportunities', $tenant), 403);
        $data = $request->validate([
            'line_ids' => ['required', 'array', 'min:1', 'max:200'],
            'line_ids.*' => ['required', 'uuid', 'distinct'],
        ]);
        return response()->json(['data' => $action->execute($tenant, $request->user(), $orderId, $data['line_ids'])], 201);
    }
}
