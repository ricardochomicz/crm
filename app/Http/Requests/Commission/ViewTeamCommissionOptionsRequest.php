<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class ViewTeamCommissionOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant !== null && (
            (bool) $this->user()?->can('viewTeamCommissions', $tenant)
            || (bool) $this->user()?->can('viewCommercialGoals', $tenant)
        );
    }

    public function rules(): array
    {
        return [];
    }
}
