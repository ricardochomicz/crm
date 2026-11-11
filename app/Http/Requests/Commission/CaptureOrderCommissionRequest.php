<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaptureOrderCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageCommissions', $tenant);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'participants' => ['sometimes', 'array', 'min:1', 'max:5'],
            'participants.*' => ['required', 'array:role,tenant_user_id,commercial_participant_id'],
            'participants.*.role' => ['required', 'distinct', Rule::in(['CONSULTANT', 'SUPERVISOR', 'MANAGER', 'PARTNER', 'REFERRER'])],
            'participants.*.tenant_user_id' => ['nullable', 'uuid'],
            'participants.*.commercial_participant_id' => ['nullable', 'uuid'],
        ];
    }
}
