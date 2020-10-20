<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOpportunity extends Model
{
    protected $casts = [
        'product' => 'array',
    ];

    protected $guarded = [
        'id'
    ];
}
