<?php

namespace App\Models;

use App\Enums\TenantInvitationStatus;
use App\Enums\TenantUserRole;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'email',
    'role',
    'tenant_role_id',
    'status',
    'token_hash',
    'expires_at',
    'accepted_at',
    'revoked_at'
])]
#[Hidden(['token_hash'])]
class TenantInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\TenantInvitationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'role' => TenantUserRole::class,
            'status' => TenantInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenantRole(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class, 'tenant_role_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === TenantInvitationStatus::Pending
            && $this->expires_at->isFuture();
    }
}
