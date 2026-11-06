<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TeamCommissionRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'operator_id', 'order_type', 'commercial_role',
        'percentage', 'starts_on', 'ends_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
