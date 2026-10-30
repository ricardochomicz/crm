<?php

namespace App\Models;

use App\Enums\TeamRole;
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
    'joined_at',
    'team_id',
    'team_role'
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
            'joined_at' => 'datetime',
            'team_role' => TeamRole::class,
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
