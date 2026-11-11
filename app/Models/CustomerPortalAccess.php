<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerPortalAccess extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'client_id', 'client_contact_id', 'challenge_hash', 'request_hash', 'code_hash', 'code_expires_at', 'attempts', 'session_hash', 'session_expires_at', 'used_at'];

    protected $hidden = ['challenge_hash', 'code_hash', 'session_hash'];

    protected $casts = ['code_expires_at' => 'datetime', 'session_expires_at' => 'datetime', 'used_at' => 'datetime'];
}
