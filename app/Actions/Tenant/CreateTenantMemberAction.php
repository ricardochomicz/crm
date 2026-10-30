<?php

namespace App\Actions\Tenant;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenantMemberAction
{
    /**
     * @param array{name: string, email: string, role: string} $data
     */
    public function handle(User $owner, Tenant $tenant, array $data): User
    {
        $role = TenantUserRole::from($data['role']);

        if ($role === TenantUserRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Não é permitido cadastrar outro proprietário.',
            ]);
        }

        try {
            return DB::transaction(function () use (
                $owner,
                $tenant,
                $data,
                $role,
            ): User {
                $subscription = $tenant->subscription()
                    ->lockForUpdate()
                    ->first();

                Gate::forUser($owner)->authorize('addMember', $tenant);

                if ($subscription === null) {
                    throw ValidationException::withMessages([
                        'subscription' => 'O tenant não possui assinatura.',
                    ]);
                }

                if (! $subscription->allowsOperationalWrites()) {
                    throw ValidationException::withMessages([
                        'subscription' => 'A assinatura não permite cadastrar membros.'
                    ]);
                }

                if ($tenant->users()->count() >= $subscription->user_limit) {
                    throw ValidationException::withMessages([
                        'user_limit' => 'O limite de usuários da assinatura foi atingido.',
                    ]);
                }

                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Str::random(64),
                ]);

                $tenant->users()->attach($user->getKey(), [
                    'role' => $role->value,
                    'status' => TenantUserStatus::Active->value,
                    'joined_at' => now(),
                ]);

                return $user;
            }, attempts: 5);
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
