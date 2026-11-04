<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class RevokeTenantInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('active_tenant');

        return $tenant instanceof Tenant
            && ($this->user()?->can('revokeInvitation', $tenant) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
