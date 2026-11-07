<?php

namespace App\Http\Requests\Opportunity;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        if (! $tenant instanceof Tenant) return false;
        $ability = match ($this->method()) {
            'POST' => 'createOpportunities',
            'PATCH' => 'updateOpportunities',
            default => 'viewOpportunities',
        };
        return $this->user()?->can($ability, $tenant) ?? false;
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) return [
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['open', 'won', 'lost', 'cancelled'])],
            'funnel_stage_id' => ['sometimes', 'uuid'],
        ];
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return [
            'code' => [$required, 'string', 'max:40'],
            'title' => [$required, 'string', 'max:200'],
            'client_id' => [$required, 'uuid'],
            'tenant_user_id' => ['sometimes', 'uuid'],
            'funnel_stage_id' => ['nullable', 'uuid'],
            'operator_id' => ['nullable', 'integer'],
            'order_type' => [$required, 'string', 'max:40'],
            'description' => ['nullable', 'string'],
            'external_protocol' => ['nullable', 'string', 'max:100'],
            'expected_close_at' => ['nullable', 'date'],
            'estimated_monthly_amount' => ['sometimes', 'numeric', 'min:0'],
            'estimated_one_time_amount' => ['sometimes', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
