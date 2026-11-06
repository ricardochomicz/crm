<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialOrderParticipant extends Model
{
    protected $fillable = ['commercial_order_id', 'role', 'tenant_user_id', 'commercial_participant_id', 'participant_name'];
}
