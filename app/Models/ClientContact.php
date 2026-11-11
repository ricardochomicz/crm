<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ClientContact extends Model
{
    use HasUuids;
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when($filters['search'] ?? null, fn(Builder $q, $v) => $q->whereAny(['name', 'email', 'phone'], 'LIKE', '%' . $v . '%'))->when(isset($filters['is_active']), fn(Builder $q) => $q->where('is_active', $filters['is_active']));
    }
    protected $fillable = ['tenant_id', 'client_id', 'name', 'position', 'phone', 'email', 'is_primary', 'is_active', 'portal_access_enabled', 'notes'];
    protected $casts = ['is_primary' => 'boolean', 'is_active' => 'boolean', 'portal_access_enabled' => 'boolean'];
}
