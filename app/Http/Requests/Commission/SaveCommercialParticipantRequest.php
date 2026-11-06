<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCommercialParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageCommissions', $tenant);
    }

    public function rules(): array
    {
        $tenant = $this->attributes->get('active_tenant');

        return [
            'role' => ['required', Rule::in(['PARTNER', 'REFERRER'])],
            'person_type' => ['required', Rule::in(['PF', 'PJ'])],
            'name' => ['required', 'string', 'max:200'],
            'document' => ['nullable', 'string', 'regex:/^\\d{11}(\\d{3})?$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
