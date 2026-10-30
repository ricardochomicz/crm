<?php

namespace App\Http\Requests\Auth;

use App\Enums\TenantType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('web') === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique(User::class, 'email'),
            ],
            'password' => [
                'required',
                'string',
                'max:128',
                'confirmed',
                Password::min(8),
            ],
            'subscription_plan_id' => [
                'required',
                'uuid',
                Rule::exists(SubscriptionPlan::class, 'id'),
            ],
            'tenant' => [
                'required',
                'array:name,type,document,email,trade_name,phone,timezone',
            ],
            'tenant.name' => ['required', 'string', 'max:180'],
            'tenant.type' => ['required', Rule::enum(TenantType::class)],
            'tenant.document' => [
                'required',
                'string',
                Rule::when(
                    $this->input('tenant.type') === TenantType::Individual->value,
                    ['regex:/\A[0-9]{11}\z/'],
                    ['regex:/\A[0-9]{14}\z/'],
                ),
            ],
            'tenant.email' => ['required', 'string', 'email', 'max:254'],
            'tenant.trade_name' => ['nullable', 'string', 'max:180'],
            'tenant.phone' => ['nullable', 'string', 'max:20'],
            'tenant.timezone' => ['sometimes', 'required', 'timezone', 'max:64'],
        ];
    }
}
