<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientSeller extends Model {
 use HasUuids;
 protected $fillable=['tenant_id','client_id','tenant_user_id','assigned_by','started_at','ended_at','reason'];
 protected $casts=['started_at'=>'datetime','ended_at'=>'datetime'];
 public function membership(){return $this->belongsTo(TenantUser::class,'tenant_user_id')->with('user:id,name,email');}
}
