<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatorClassification extends Model {
 protected $fillable=['operator_id','tenant_id','code','name','description','is_active','sort_order'];
 protected $casts=['is_active'=>'boolean','sort_order'=>'integer'];
 public function operator(){return $this->belongsTo(Operator::class);}
 public function rules(){return $this->hasMany(ClassificationRenewalRule::class,'classification_id');}
}
