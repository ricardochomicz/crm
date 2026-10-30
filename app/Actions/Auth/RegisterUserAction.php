<?php

namespace App\Actions\Auth;

use App\Actions\Tenant\CreateTenantAction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class RegisterUserAction
{

    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     * } $data
     */

    public function handle(array $data): User
    {
        try {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::query()->where('email', $data['email'])->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'email' => 'Este e-mail já possui cadastro.',
            ]);
        }
    }
}
