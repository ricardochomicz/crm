<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\TenantInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email', '')))]);
    }

    protected function passedValidation(): void
    {
        $token = $this->input('invitation_token');
        if ($token === null) {
            return;
        }

        $invitation = TenantInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isAvailable()
            || Str::lower(trim($invitation->email)) !== $this->input('email')) {
            throw ValidationException::withMessages([
                'email' => 'O e-mail deve corresponder ao convite válido recebido.',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'invitation_token' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9]{64}$/'],
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
        ];
    }
}
