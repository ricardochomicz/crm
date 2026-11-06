<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'description',
    'monthly_price',
    'currency',
    'user_limit',
    'storage_limit_mb',
    'is_active'
])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPlanFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'user_limit' => 'integer',
            'storage_limit_mb' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when(array_key_exists('is_active', $filters), fn (Builder $query): Builder => $query->where('is_active', $filters['is_active']));
    }
}
