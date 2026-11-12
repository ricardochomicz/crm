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


        return DB::transaction(function () use ($client, $order, $contract): ClientContract {
            $lockedOrder = CommercialOrder::query()
                ->where('tenant_id', $client->tenant_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($contract) {
                $contract = ClientContract::query()
                    ->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)
                    ->lockForUpdate()
                    ->findOrFail($contract->id);

                if ((int) $contract->operator_id !== (int) $lockedOrder->operator_id) {
                    throw ValidationException::withMessages([
                        'contract_id' => 'A operadora do contrato é diferente da operadora do pedido. Não é permitido vincular pedidos de operadoras distintas.',
                    ]);
                }

                if ($lockedOrder->classification_id && $contract->classification_id
                    && (int) $lockedOrder->classification_id !== (int) $contract->classification_id) {
                    throw ValidationException::withMessages([
                        'contract_id' => 'A classificação do contrato é incompatível com a classificação do pedido.',
                    ]);
                }
            }

            if ($lockedOrder->client_contract_id) {
                throw ValidationException::withMessages(['order_id' => 'O pedido já está vinculado a um contrato e não pode ser consolidado novamente.']);
            }

            if (! $contract) {
                $operatorName = Operator::query()->whereKey($lockedOrder->operator_id)->value('name');
                $policy = mb_strtolower(trim((string) $operatorName)) === 'claro'
                    ? ClientContract::POLICY_WEIGHTED_AVERAGE
                    : ClientContract::POLICY_PENDING;

                $contract = ClientContract::query()->create([
                    'tenant_id' => $client->tenant_id,
                    'client_id' => $client->id,
                    'operator_id' => $lockedOrder->operator_id,
                    'classification_id' => $lockedOrder->classification_id
                        ?: ((int) $client->operator_id === (int) $lockedOrder->operator_id ? $client->classification_id : null),
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
