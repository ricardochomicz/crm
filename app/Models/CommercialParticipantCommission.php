<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommercialParticipantCommission extends Model
{
 use HasUuids;
 protected $fillable = ['tenant_id','commercial_participant_id','operator_id','order_type','percentage','starts_on','ends_on','is_active'];
 protected function casts(): array { return ['percentage'=>'decimal:2','starts_on'=>'date:Y-m-d','ends_on'=>'date:Y-m-d','is_active'=>'boolean']; }
 public function operator() { return $this->belongsTo(Operator::class); }
 public function participant() { return $this->belongsTo(CommercialParticipant::class,'commercial_participant_id'); }
}
