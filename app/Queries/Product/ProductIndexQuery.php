<?php

namespace App\Queries\Product;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductIndexQuery
{
    public function get(string $tenantId, array $filters): Collection
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->filter($filters)
            ->with(['operator:id,name', 'promotions'])
            ->orderBy('name')
            ->get();
    }
}
