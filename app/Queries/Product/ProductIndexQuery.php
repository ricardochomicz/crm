<?php

namespace App\Queries\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductIndexQuery
{
    public function get(string $tenantId, array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->filter($filters)
            ->with(['operator:id,name', 'promotions'])
            ->orderBy('name')
            ->paginate(15);
    }
}
