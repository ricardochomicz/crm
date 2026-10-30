<?php

namespace App\Actions\Auth;

use App\Actions\Tenant\CreateTenantAction;
use App\Enums\TenantType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUserAction
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        private CreateTenantAction $createTenantAction
    ) {
        //
    }

    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     subscription_plan_id: string,
     *     tenant: array{
     *         name: string,
     *         type: string,
     *         document: string,
     *         email: string,
     *         trade_name?: string|null,
     *         phone?: string|null,
     *         timezone?: string
     *     }
     * } $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $tenantData = $data['tenant'];
            $tenantData['type'] = TenantType::from($tenantData['type']);

            $this->createTenantAction->handle(
                $user,
                $data['subscription_plan_id'],
                $tenantData,
            );

            return $user;
        });
    }
}
