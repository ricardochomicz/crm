<?php

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FilterProductsAction
{
    public function execute(string $tenantId, array $filters): LengthAwarePaginator
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->filter($filters)
            ->with(['operator:id,name', 'promotions'])
            ->orderBy('name')->orderBy('id')
            ->paginate($filters['per_page'] ?? 15);
    }
}
