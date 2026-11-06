<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialGoal extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'operator_id', 'order_type', 'commercial_role', 'metric', 'starts_on', 'ends_on', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];

    public function tiers(): HasMany
    {
        return $this->hasMany(CommercialGoalTier::class)->orderBy('minimum');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }
        if (isset($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }
        if (isset($filters['commercial_role'])) {
            $query->where('commercial_role', $filters['commercial_role']);
        }
        if (isset($filters['metric'])) {
            $query->where('metric', $filters['metric']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query;
    }
}
