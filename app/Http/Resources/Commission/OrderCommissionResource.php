<?php

namespace App\Http\Resources\Commission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderCommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->commercial_order_id,
            'item_id' => $this->source_item_id,
            'revision' => (int) $this->revision,
            'is_current' => (bool) $this->is_current,
            'status' => $this->status,
            'source' => $this->source,
            'captured_by' => $this->captured_by,
            'reason' => $this->reason,
            'sold_on' => $this->sold_on,
            'activated_on' => $this->activated_on,
            'operator_id' => (int) $this->operator_id,
            'order_type' => $this->order_type,
            'line_number' => $this->line_number,
            'description' => $this->description,
            'quantity' => (int) $this->quantity,
            'base_amount' => $this->base_amount,
            'gross_amount' => $this->gross_amount,
            'deduction_amount' => $this->deduction_amount,
            'net_amount' => $this->net_amount,
            'participant_total' => $this->participant_total,
            'company_balance' => $this->company_balance,
            'rules' => json_decode($this->rule_snapshot, true, flags: JSON_THROW_ON_ERROR),
            'participants' => json_decode($this->participant_amounts, true, flags: JSON_THROW_ON_ERROR),
            'issues' => json_decode($this->issues, true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $this->created_at,
        ];
    }
}
