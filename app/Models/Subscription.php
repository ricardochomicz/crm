<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'subscription_plan_id',
    'status',
    'monthly_price',
    'currency',
    'user_limit',
    'storage_limit_mb',
    'started_at',
    'trial_ends_at',
    'current_period_starts_at',
    'current_period_ends_at',
    'grace_ends_at',
    'cancel_at_period_end',
    'cancelled_at',
])]
class Subscription extends Model
{
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'monthly_price' => 'decimal:2',
            'user_limit' => 'integer',
            'storage_limit_mb' => 'integer',
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, mixed $status) => $q->where('status', $status))
            ->when($filters['subscription_plan_id'] ?? null, fn (Builder $q, string $planId) => $q->where('subscription_plan_id', $planId));
    }

    public function allowsOperationalWrites(
        ?CarbonInterface $at = null
    ): bool {
        $at ??= now();

        [$startsAt, $endsAt] = match ($this->status) {
            SubscriptionStatus::Trialing => [
                $this->started_at,
                $this->trial_ends_at,
            ],
            SubscriptionStatus::Active => [
                $this->current_period_starts_at,
                $this->current_period_ends_at,
            ],
            default => [null, null],
        };

        if ($startsAt === null || $endsAt === null) {
            return false;
        }

        return $startsAt->lessThanOrEqualTo($at)
            && $endsAt->greaterThan($at);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'subscription_plan_id',
        );
    }
}
