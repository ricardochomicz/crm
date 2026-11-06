<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialParticipantCommission extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'commercial_participant_id', 'operator_id', 'order_type', 'percentage', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2', 'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'is_active' => 'boolean'];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CommercialParticipant::class, 'commercial_participant_id');
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
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query;
    }
}
