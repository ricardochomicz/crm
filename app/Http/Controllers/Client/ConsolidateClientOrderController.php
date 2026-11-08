<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\AttachOrderToClientContractAction;
use App\Actions\Client\ScopeVisibleClientsAction;
use App\Http\Controllers\Controller;
use App\Models\ClientContract;
use App\Models\CommercialOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsolidateClientOrderController extends Controller
{
    public function __invoke(
        Request $request,
        string $tenantId,
        string $clientId,
        string $orderId,
        ScopeVisibleClientsAction $visibility,
        AttachOrderToClientContractAction $attach,
    ): JsonResponse {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('updateClients', $tenant), 403);

        $data = $request->validate([
            'contract_id' => ['nullable', 'uuid', Rule::exists('client_contracts', 'id')
                ->where('tenant_id', $tenant->id)->where('client_id', $clientId)],
        ]);

        $client = $visibility->findOrFail($tenant->id, $request->user()->id, $clientId);
        $order = CommercialOrder::query()->where('tenant_id', $tenant->id)->findOrFail($orderId);
        $contract = isset($data['contract_id'])
            ? ClientContract::query()->where('tenant_id', $tenant->id)->where('client_id', $client->id)
                ->findOrFail($data['contract_id'])
            : null;

        $attached = $attach->execute($client, $order, $contract);

        return response()->json(['data' => [
            'contract_id' => $attached->id,
            'order_id' => $order->id,
            'renewal_policy' => $attached->renewal_policy,
        ]]);
    }
}
