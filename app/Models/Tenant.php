<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

use App\Enums\TenantStatus;
use App\Enums\TenantType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'type', 'document', 'trade_name', 'email', 'phone', 'logo', 'status', 'timezone'])]
class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TenantType::class,
            'status' => TenantStatus::class,

        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(TenantRole::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->using(TenantUser::class)
            ->as('membership')
            ->withPivot(['id', 'role', 'tenant_role_id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'tenant_operators')->withTimestamps();
    }

    public function commissionTables(): HasMany
    {
        return $this->hasMany(CommissionTable::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(TenantUnit::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }
    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['document'])) {
            $query->where('document', $filters['document']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }
}
