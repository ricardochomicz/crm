<?php

namespace App\Models;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'tenant_id',
    'user_id',
    'role',
    'status',
    'joined_at'
])]
class TenantUser extends Pivot
{
    use HasUuids;

    protected $table = 'tenant_users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantUserRole::class,
            'status' => TenantUserStatus::class,
            'joined_at' => 'datetime'
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
