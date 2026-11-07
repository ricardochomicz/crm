<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialOrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'commercial_order_id', 'opportunity_item_id', 'product_id', 'operator_id',
        'order_type_id', 'line_number', 'description', 'quantity',
        'monthly_unit_amount', 'one_time_unit_amount', 'status', 'activated_at', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'activated_at' => 'date:Y-m-d',
        'monthly_unit_amount' => 'decimal:2',
        'one_time_unit_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommercialOrder::class, 'commercial_order_id');
    }
}
