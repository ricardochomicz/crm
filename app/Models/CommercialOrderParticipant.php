<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommercialOrderParticipant extends Model
{
    protected $fillable = ['commercial_order_id', 'role', 'tenant_user_id', 'commercial_participant_id', 'participant_name'];

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['commercial_order_id'])) {
            $query->where('commercial_order_id', $filters['commercial_order_id']);
        }
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (isset($filters['tenant_user_id'])) {
            $query->where('tenant_user_id', $filters['tenant_user_id']);
        }
        if (isset($filters['commercial_participant_id'])) {
            $query->where('commercial_participant_id', $filters['commercial_participant_id']);
        }

        return $query;
    }
}
