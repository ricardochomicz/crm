<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\ScopeVisibleClientsAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContract;
use App\Models\CommercialOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientContractConsolidationOptionsController extends Controller
{
    public function __invoke(Request $request, string $tenantId, string $clientId, ScopeVisibleClientsAction $visibility): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('updateClients', $tenant), 403);

        $client = $visibility->findOrFail($tenant->id, $request->user()->id, $clientId);

        $orders = CommercialOrder::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('administratively_completed_at')
            ->whereNull('client_contract_id')
            ->where(function ($query) use ($client) {
                $query->where('client_id', $client->id)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('client_id')
                        ->where('customer_document', $client->document));
            })
            ->with('operator:id,name')
            ->orderByDesc('administratively_completed_at')
            ->get(['id', 'code', 'client_id', 'operator_id', 'classification_id', 'line_quantity', 'monthly_amount', 'administratively_completed_at']);

        $contracts = ClientContract::query()
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->with('operator:id,name')
            ->get(['id', 'operator_id', 'classification_id', 'renewal_policy', 'status']);

        return response()->json(['data' => [
            'orders' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'code' => $order->code,
                'operator_id' => $order->operator_id,
                'operator_name' => $order->operator?->name,
                'classification_id' => $order->classification_id,
                'line_quantity' => $order->line_quantity,
                'monthly_amount' => $order->monthly_amount,
            ])->values(),
            'contracts' => $contracts->map(fn ($contract) => [
                'id' => $contract->id,
                'operator_id' => $contract->operator_id,
                'operator_name' => $contract->operator?->name,
                'classification_id' => $contract->classification_id,
                'renewal_policy' => $contract->renewal_policy,
                'status' => $contract->status,
            ])->values(),
        ]]);
    }
}
