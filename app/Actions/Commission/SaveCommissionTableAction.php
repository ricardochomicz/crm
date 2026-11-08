<?php

namespace App\Actions\Commission;

use App\Enums\OrderType;
use App\Models\CommissionTable;
use Illuminate\Validation\ValidationException;

class SaveCommissionTableAction
{
    /** @param array<string, mixed> $data */
    public function execute(string $tenantId, array $data, ?string $commissionId = null): CommissionTable
    {
        $query = CommissionTable::query()->where('tenant_id', $tenantId);
        $record = $commissionId ? (clone $query)->findOrFail($commissionId) : null;

        $duplicate = (clone $query)->where('operator_id', $data['operator_id'])
            ->where('order_type', $data['order_type']);

        if ($record) {
            $duplicate->where('id', '!=', $record->id);
        }

        if ($duplicate->exists()) {
            throw ValidationException::withMessages([
                'order_type' => 'Já existe comissão para esta operadora e tipo de pedido.',
            ]);
        }

        $data['name'] = OrderType::tryFrom($data['order_type'])?->label()
            ?? \App\Models\OrderType::query()->findOrFail($data['order_type'])->name;

        if ($record) {
            $record->update($data);
        } else {
            $record = CommissionTable::create(['tenant_id' => $tenantId, ...$data]);
        }

        return $record->fresh('operator:id,name');
    }
}
