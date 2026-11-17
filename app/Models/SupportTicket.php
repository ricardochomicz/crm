<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'category', 'subject', 'description', 'status'];
}
