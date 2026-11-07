<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'is_initial'];

    protected $casts = ['is_initial' => 'boolean'];
}
