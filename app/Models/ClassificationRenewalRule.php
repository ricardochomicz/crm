<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ClassificationRenewalRule extends Model {
 use HasUuids;
 public function scopeFilter(Builder $query,array $filters):Builder {return $query->when($filters['classification_id']??null,fn(Builder $q,$v)=>$q->where('classification_id',$v))->when(isset($filters['is_active']),fn(Builder $q)=>$q->where('is_active',$filters['is_active']));}
 protected $fillable=['classification_id','tenant_id','minimum_months','valid_from','valid_until','is_active','notes'];
 protected $casts=['is_active'=>'boolean','minimum_months'=>'integer','valid_from'=>'date:Y-m-d','valid_until'=>'date:Y-m-d'];
}
