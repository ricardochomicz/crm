<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassificationRenewalRule extends Model {
 use HasUuids;
 protected $fillable=['classification_id','tenant_id','minimum_months','valid_from','valid_until','is_active','notes'];
 protected $casts=['is_active'=>'boolean','minimum_months'=>'integer','valid_from'=>'date:Y-m-d','valid_until'=>'date:Y-m-d'];
}
