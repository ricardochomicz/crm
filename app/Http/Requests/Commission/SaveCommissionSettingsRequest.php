<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class SaveCommissionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageCommissions', $tenant);
    }

    public function rules(): array
    {
        return ['default_deduction_percentage' => ['required', 'numeric', 'min:0', 'max:100']];
    }
}
