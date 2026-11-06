<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialParticipant extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['tenant_id', 'role', 'person_type', 'name', 'document', 'email', 'phone', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(CommercialParticipantCommission::class);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (filled($filters['search'] ?? null)) {
            $query->where('name', 'like', '%'.addcslashes(trim($filters['search']), '%_\\').'%');
        }
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query;
    }
}
