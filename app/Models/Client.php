<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Client extends Model {
 use HasUuids;
 protected $fillable=['tenant_id','person_type','document','company_name','phone','email','zip_code','address','address_number','address_complement','neighborhood','city','state','operator_id','classification_id','contract_started_on','contract_ends_on','is_active'];
 protected $casts=['is_active'=>'boolean','contract_started_on'=>'date:Y-m-d','contract_ends_on'=>'date:Y-m-d'];
 public function operator(){return $this->belongsTo(Operator::class);}
 public function classification(){return $this->belongsTo(OperatorClassification::class,'classification_id');}
 public function contacts(){return $this->hasMany(ClientContact::class);}
 public function sellers(){return $this->hasMany(ClientSeller::class);}
 public function operatorHistory(){return $this->hasMany(ClientOperatorHistory::class);}
}
