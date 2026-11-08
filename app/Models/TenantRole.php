<?php

namespace App\Models;

use App\Enums\TenantPermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'code', 'name', 'legacy_role', 'permissions', 'is_system', 'is_active'])]
class TenantRole extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'permissions' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when($filters['search'] ?? null, fn (Builder $q, string $search): Builder => $q->where('name', 'LIKE', '%'.$search.'%'))
            ->when(array_key_exists('is_active', $filters), fn (Builder $q): Builder => $q->where('is_active', $filters['is_active']));
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        return $this->code === 'OWNER' ? TenantPermission::keys() : array_values(array_intersect($this->permissions ?? [], TenantPermission::keys()));
    }
}
