<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommercialGoalTier extends Model
{
    protected $fillable = ['commercial_goal_id', 'minimum', 'percentage'];
}
