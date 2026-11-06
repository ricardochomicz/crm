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

    /** @param array<string, mixed> $filters */
    public function scopeFilter(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        return $query;
    }
}
