<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionTable extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'operator_id', 'name', 'order_type', 'percentage', 'deduction_percentage', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2', 'deduction_percentage' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (filled($filters['search'] ?? null)) {
            $query->where('name', 'like', '%' . addcslashes(trim($filters['search']), '%_\\') . '%');
        }
        if (isset($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }
        if (isset($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query;
    }
}
