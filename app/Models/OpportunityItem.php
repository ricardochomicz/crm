<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'opportunity_id', 'product_id', 'operator_id', 'origin_operator_id',
        'description', 'line_number', 'order_type_id', 'operation_type', 'quantity', 'monthly_unit_amount', 'one_time_unit_amount', 'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'monthly_unit_amount' => 'decimal:2', 'one_time_unit_amount' => 'decimal:2'];
    }

    public function orderType(): BelongsTo { return $this->belongsTo(OrderType::class); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function operator(): BelongsTo { return $this->belongsTo(Operator::class); }
}
