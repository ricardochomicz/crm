<?php

namespace App\Models;

use App\Enums\OrderStatusCode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'code', 'is_initial'];

    protected $casts = ['is_initial' => 'boolean', 'code' => OrderStatusCode::class];
}
