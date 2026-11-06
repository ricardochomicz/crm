<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model {
 use HasUuids;
 protected $fillable=['tenant_id','client_id','name','position','phone','email','is_primary','is_active','notes'];
 protected $casts=['is_primary'=>'boolean','is_active'=>'boolean'];
}
