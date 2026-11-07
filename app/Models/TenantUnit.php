<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'address', 'city', 'state', 'is_headquarters'])]
class TenantUnit extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_headquarters' => 'boolean'];
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn(Builder $q, string $search) => $q->where(function (Builder $sub) use ($search) {
                $sub->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('city', 'LIKE', '%' . $search . '%')
                    ->orWhere('state', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%');
            }))
            ->when(array_key_exists('is_headquarters', $filters), fn(Builder $q) => $q->where('is_headquarters', $filters['is_headquarters']));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(TenantUser::class, 'tenant_unit_memberships', 'tenant_unit_id', 'membership_id')->withPivot('tenant_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
