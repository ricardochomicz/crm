<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class CaptureOrderCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (bool) $this->user()?->can('manageCommissions', $tenant);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:1000']];
    }
}
