<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'name', 'supervisor_membership_id', 'tenant_unit_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasUuids;

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TenantUnit::class, 'tenant_unit_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supervisorMembership(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'supervisor_membership_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }
    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%' . addcslashes(trim($filters['name']), '%_\\\\') . '%');
        }

        if (isset($filters['supervisor_membership_id'])) {
            $query->where(
                'supervisor_membership_id',
                $filters['supervisor_membership_id'],
            );
        }

        return $query;
    }
}
