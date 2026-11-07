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
            'status' => ['sometimes', Rule::in(['open', 'won', 'lost', 'cancelled', 'all'])],
            'period' => ['sometimes', Rule::in(['current', 'overdue', 'future', 'all', 'custom'])],
            'order_type_id' => ['sometimes', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            'funnel_stage_id' => ['sometimes', 'uuid'],
        ];
        if ($this->routeIs('opportunities.stage.move')) return ['funnel_stage_id' => ['required', 'uuid']];
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return [
            'code' => [$required, 'string', 'max:40'],
            'title' => [$required, 'string', 'max:200'],
            'client_id' => [$required, 'uuid'],
            'tenant_user_id' => ['sometimes', 'uuid'],
            'funnel_stage_id' => ['nullable', 'uuid'],
            'operator_id' => ['nullable', 'integer'],
            'order_type_id' => [$required, 'uuid'],
            'description' => ['nullable', 'string'],
            'external_protocol' => ['nullable', 'string', 'max:100'],
            'expected_close_at' => ['nullable', 'date'],
            'estimated_monthly_amount' => ['sometimes', 'numeric', 'min:0'],
            'estimated_one_time_amount' => ['sometimes', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'items' => [$required, 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.order_type_id' => ['required', 'uuid'],
            'items.*.line_number' => ['nullable', 'string', 'max:20'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.monthly_unit_amount' => ['required', 'numeric', 'min:0'],
            'items.*.one_time_unit_amount' => ['sometimes', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:10000'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
