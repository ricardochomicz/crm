<?php

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\ClientContract;
use App\Models\CommercialOrder;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachOrderToClientContractAction
{
    public function execute(Client $client, CommercialOrder $order, ?ClientContract $contract = null): ClientContract
    {
        if ($client->tenant_id !== $order->tenant_id) {
            throw ValidationException::withMessages(['order_id' => 'O pedido pertence a outra empresa.']);
        }

        if ($order->client_id && $order->client_id !== $client->id) {
            throw ValidationException::withMessages(['order_id' => 'O pedido pertence a outro cliente.']);
        }

        if (! $order->client_id && $order->customer_document !== $client->document) {
            throw ValidationException::withMessages(['order_id' => 'O documento do pedido não corresponde ao cliente.']);
        }

        if ($contract && (
            $contract->tenant_id !== $client->tenant_id
            || $contract->client_id !== $client->id
            || (int) $contract->operator_id !== (int) $order->operator_id
            || ($order->classification_id && $contract->classification_id
                && (int) $order->classification_id !== (int) $contract->classification_id)
        )) {
            throw ValidationException::withMessages(['contract_id' => 'Contrato incompatível com o pedido.']);
        }

        return DB::transaction(function () use ($client, $order, $contract): ClientContract {
            $lockedOrder = CommercialOrder::query()
                ->where('tenant_id', $client->tenant_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->client_contract_id && $lockedOrder->client_contract_id !== $contract?->id) {
                throw ValidationException::withMessages(['order_id' => 'O pedido já está associado a outro contrato.']);
            }

            if (! $contract) {
                $operatorName = Operator::query()->whereKey($order->operator_id)->value('name');
                $policy = mb_strtolower(trim((string) $operatorName)) === 'claro'
                    ? ClientContract::POLICY_WEIGHTED_AVERAGE
                    : ClientContract::POLICY_PENDING;

                $contract = ClientContract::query()->create([
                    'tenant_id' => $client->tenant_id,
                    'client_id' => $client->id,
                    'operator_id' => $order->operator_id,
                    'classification_id' => $order->classification_id
                        ?: ((int) $client->operator_id === (int) $order->operator_id ? $client->classification_id : null),
                    'renewal_policy' => $policy,
                    'status' => 'ACTIVE',
                ]);
            }

            $lockedOrder->update([
                'client_id' => $client->id,
                'client_contract_id' => $contract->id,
            ]);

            return $contract;
        });
    }
}
