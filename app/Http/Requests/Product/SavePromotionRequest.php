<?php

namespace App\Http\Requests\Product;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class SavePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        return $tenant instanceof Tenant && $this->user()->can('managePromotions', $tenant);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'promotional_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
