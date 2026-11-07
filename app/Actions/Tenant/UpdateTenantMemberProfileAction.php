<?php

namespace App\Actions\Tenant;

use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;

class UpdateTenantMemberProfileAction
{
    /** @param array<string, mixed> $data */
    public function execute(TenantUser $membership, array $data): TenantUser
    {
        return DB::transaction(function () use ($membership, $data): TenantUser {
            $membership->fill($data);
            $membership->save();

            return $membership->refresh()->load('user:id,name,email');
        });
    }
}
