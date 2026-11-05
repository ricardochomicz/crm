<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductPromotion extends Model
{
    use HasUuids;
    protected $fillable = ['product_id', 'name', 'promotional_price', 'starts_at', 'ends_at', 'is_active'];
    protected function casts(): array
    {
        return ['promotional_price' => 'decimal:2', 'starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d', 'is_active' => 'boolean'];
    }
}
