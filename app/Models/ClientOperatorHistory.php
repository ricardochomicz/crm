<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientOperatorHistory extends Model {
 use HasUuids;
 protected $table='client_operator_history';
 protected $fillable=['tenant_id','client_id','operator_id','classification_id','changed_at','changed_by'];
 protected $casts=['changed_at'=>'datetime'];
 public function operator(){return $this->belongsTo(Operator::class);}
 public function classification(){return $this->belongsTo(OperatorClassification::class,'classification_id');}
}
