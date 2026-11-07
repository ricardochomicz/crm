<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialOrder extends Model
{
    use HasUuids;

    protected $fillable = ['opportunity_id', 'tenant_id', 'order_status_id', 'code', 'tenant_user_id', 'client_id', 'expected_activation_at', 'activated_at', 'operator_id', 'product_id', 'order_type', 'customer_name', 'customer_document', 'monthly_amount', 'line_quantity', 'status', 'sold_on', 'notes'];

    protected $casts = ['monthly_amount' => 'decimal:2', 'line_quantity' => 'integer', 'sold_on' => 'date:Y-m-d'];

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommercialOrderItem::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CommercialOrderParticipant::class);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (filled($filters['search'] ?? null)) {
            $query->where('customer_name', 'like', '%' . addcslashes(trim($filters['search']), '%_\\') . '%');
        }
        if (isset($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }
        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (isset($filters['order_type'])) {
            $query->where('order_type', $filters['order_type']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }
}
