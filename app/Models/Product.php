<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'operator_id', 'name', 'description', 'category', 'price', 'is_active'];
    protected function casts(): array
    {
        return ['category' => ProductCategory::class, 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
    public function promotions()
    {
        return $this->hasMany(ProductPromotion::class);
    }
}
