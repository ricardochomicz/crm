<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommercialParticipant extends Model
{
 use HasUuids;
 protected $fillable = ['tenant_id','role','person_type','name','document','email','phone','is_active','notes'];
 protected function casts(): array { return ['is_active' => 'boolean']; }
 public function commissions() { return $this->hasMany(CommercialParticipantCommission::class); }
}
