<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionDeflatorTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'operator_id', 'order_type',
        'reduction_from_percentage', 'reduction_to_percentage',
        'commission_deflator_percentage', 'starts_on', 'ends_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reduction_from_percentage' => 'decimal:4',
            'reduction_to_percentage' => 'decimal:4',
            'commission_deflator_percentage' => 'decimal:4',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
