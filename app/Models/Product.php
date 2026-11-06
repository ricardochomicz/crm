<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'operator_id', 'name', 'description', 'category', 'price', 'is_active'];
    protected function casts(): array
    {
        return ['category' => ProductCategory::class, 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where('name', 'LIKE', '%'.$search.'%'))
            ->when($filters['operator_id'] ?? null, fn (Builder $q, $id) => $q->where('operator_id', $id))
            ->when($filters['category'] ?? null, fn (Builder $q, $category) => $q->where('category', $category))
            ->when(array_key_exists('is_active', $filters), fn (Builder $q) => $q->where('is_active', $filters['is_active']));
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
