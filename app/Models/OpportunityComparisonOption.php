<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OpportunityComparisonOption extends Model
{
    use HasUuids;

    protected $fillable = ['comparison_id', 'operator_id', 'product_id', 'operator_name', 'product_name', 'quantity', 'monthly_unit_amount', 'one_time_unit_amount', 'notes'];
    protected function casts(): array { return ['quantity' => 'integer', 'monthly_unit_amount' => 'decimal:2', 'one_time_unit_amount' => 'decimal:2']; }
}
