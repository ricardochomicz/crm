<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommercialOrder;
use App\Models\OperatorClassification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateClientContractClassificationController extends Controller
{
    public function __invoke(Request $request, string $tenantId, string $clientId, string $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('updateClients', $tenant), 403);

        $data = $request->validate([
            'classification_id' => ['required', 'integer', Rule::exists('operator_classifications', 'id')],
        ]);

        $client = Client::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($clientId);

        $order = DB::transaction(function () use ($tenant, $client, $orderId, $data): CommercialOrder {
            $order = CommercialOrder::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            // Não vincular um pedido que já pertence a outro cliente.
            if ($order->client_id && $order->client_id !== $client->id) {
                abort(404);
            }

            if (! $order->client_id && $order->customer_document !== $client->document) {
                throw ValidationException::withMessages([
                    'order_id' => 'O documento do pedido não corresponde ao cliente.',
                ]);
            }

            $classification = OperatorClassification::query()
                ->whereKey($data['classification_id'])
                ->where('operator_id', $order->operator_id)
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->first();

            if (! $classification) {
                throw ValidationException::withMessages([
                    'classification_id' => 'Classificação indisponível para a operadora deste contrato.',
                ]);
            }

            $order->update([
                'client_id' => $client->id,
                'classification_id' => $classification->id,
            ]);

            return $order;
        });

        return response()->json(['data' => [
            'order_id' => $order->id,
            'client_id' => $order->client_id,
            'classification_id' => $order->classification_id,
        ]]);
    }
}
