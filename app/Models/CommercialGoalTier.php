<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommercialGoalTier extends Model
{
    protected $fillable = ['commercial_goal_id', 'minimum', 'percentage'];

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (isset($filters['commercial_goal_id'])) {
            $query->where('commercial_goal_id', $filters['commercial_goal_id']);
        }

        return $query;
    }
}
