<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'tenant_id',
    'user_id',
    'role',
    'tenant_role_id',
    'status',
    'joined_at',
    'team_id',
    'cpf',
    'birth_date',
    'mobile_phone',
    'secondary_phone',
    'hired_at',
    'employee_code',
])]
class TenantUser extends Pivot
{
    use HasUuids;

    protected static function booted(): void
    {
        static::created(function (TenantUser $membership): void {
            $headquarters = $membership->tenant->units()->where('is_headquarters', true)->first();
            $headquarters?->members()->syncWithoutDetaching([
                $membership->id => ['tenant_id' => $membership->tenant_id],
            ]);
        });
    }

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
            'cpf' => 'encrypted',
            'birth_date' => 'date',
            'hired_at' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function supervisedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'supervisor_membership_id');
    }
    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when(isset($filters['status']), fn(Builder $query): Builder => $query->where('status', $filters['status']));
    }
}
