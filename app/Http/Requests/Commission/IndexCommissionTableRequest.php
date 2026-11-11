<?php

namespace App\Http\Requests\Commission;

use App\Rules\ValidCommissionOrderType;
use Illuminate\Foundation\Http\FormRequest;

class IndexCommissionTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('viewCommissions', $tenant);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'operator_id' => ['nullable', 'integer'],
            'order_type' => ['nullable', 'string', new ValidCommissionOrderType(activeOnly: false)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
