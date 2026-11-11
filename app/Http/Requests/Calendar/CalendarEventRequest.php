<?php

namespace App\Http\Requests\Calendar;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');
        if (! $tenant instanceof Tenant) {
            return false;
        }
        $ability = match ($this->method()) {
            'POST' => 'createCalendarEvents',
            'PATCH' => 'updateCalendarEvents',
            'DELETE' => 'deleteCalendarEvents',
            default => 'viewCalendar',
        };

        return $this->user()?->can($ability, $tenant) ?? false;
    }

    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }
        if ($this->isMethod('GET') && ($this->is('api/v1/tenants/*/calendar/assignees') || $this->is('api/v1/tenants/*/calendar/events/*/history'))) return [];
        if ($this->isMethod('GET') && $this->is('api/v1/tenants/*/activities/overview')) {
            return [
                'scope' => ['sometimes', Rule::in(['mine', 'team'])],
                'search' => ['sometimes', 'string', 'max:120'],
            ];
        }
        if ($this->isMethod('GET') && $this->is('api/v1/tenants/*/activities')) {
            return [
                'scope' => ['sometimes', Rule::in(['mine', 'team'])],
                'status' => ['sometimes', Rule::in(['pending', 'overdue', 'completed', 'cancelled', 'all'])],
                'search' => ['sometimes', 'string', 'max:120'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'page' => ['sometimes', 'integer', 'min:1'],
            ];
        }
        if ($this->isMethod('GET')) {
            return [
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after:from'],
                'scope' => ['sometimes', Rule::in(['mine', 'team'])],
                'opportunity_id' => ['sometimes', 'uuid'],
            ];
        }

        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => [$this->isMethod('POST') ? 'required' : 'sometimes', Rule::in(['meeting', 'call', 'visit', 'task', 'reminder'])],
            'status' => ['sometimes', Rule::in(['pending', 'completed', 'cancelled'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            'all_day' => ['sometimes', 'boolean'],
            'starts_at' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'date'],
            'ends_at' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'date'],
            'tenant_user_id' => ['sometimes', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'opportunity_id' => ['nullable', 'uuid'],
        ];
    }
}
