<?php

namespace App\Http\Requests\Commercial;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCommercialOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        $user = $this->user();

        return $tenant !== null && $user !== null
            && ($user->can('viewCommissions', $tenant) || $user->can('viewOpportunities', $tenant));
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'search' => ['nullable', 'string', 'max:200'],
            'operator_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'uuid'],
            'order_type' => ['nullable', Rule::enum(OrderType::class)],
            'status' => ['nullable', 'string', 'max:24'],
        ];
    }
}
