<?php

namespace App\Http\Requests\Commercial;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommercialOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageOrders', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_document' => ['nullable', 'string', 'regex:/^(\\d{11}|\\d{14})$/'],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'line_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'sold_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'participants' => ['required', 'array', 'min:1', 'max:5'],
            'participants.*.role' => ['required', Rule::in(['CONSULTANT', 'SUPERVISOR', 'MANAGER', 'PARTNER', 'REFERRER'])],
            'participants.*.tenant_user_id' => ['nullable', 'uuid'],
            'participants.*.commercial_participant_id' => ['nullable', 'uuid'],
        ];
    }
}
