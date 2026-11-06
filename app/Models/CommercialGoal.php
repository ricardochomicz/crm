<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommercialGoal extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'operator_id', 'order_type', 'commercial_role', 'metric', 'starts_on', 'ends_on', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    public function tiers() { return $this->hasMany(CommercialGoalTier::class)->orderBy('minimum'); }
    public function operator() { return $this->belongsTo(Operator::class); }
}
