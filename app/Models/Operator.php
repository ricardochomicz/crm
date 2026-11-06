<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'is_active'])]
class Operator extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->whereAny(['name', 'slug'], 'LIKE', '%'.$search.'%'))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', $filters['is_active']));
    }


    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_operators')->withTimestamps();
    }
}
