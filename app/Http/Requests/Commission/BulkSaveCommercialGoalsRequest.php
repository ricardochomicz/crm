<?php

namespace App\Http\Requests\Commission;

use Illuminate\Validation\Rule;

class BulkSaveCommercialGoalsRequest extends SaveCommercialGoalRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['target_scope'] = ['required', Rule::in(['CONSULTANT', 'SUPERVISOR'])];
        $rules['responsible_membership_id'] = ['prohibited'];
        $rules['responsible_membership_ids'] = ['required', 'array', 'min:1', 'max:100'];
        $rules['responsible_membership_ids.*'] = [
            'required', 'uuid', 'distinct',
            Rule::exists('tenant_users', 'id')
                ->where('tenant_id', $this->attributes->get('active_tenant')->id)
                ->where('status', 'ACTIVE'),
        ];

        return $rules;
    }
}
