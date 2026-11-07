<?php

namespace App\Actions\Product;

use App\Models\ProductPromotion;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProductPromotionAction
{
    /** @param array<string, mixed> $data */
    public function execute(
        string $tenantId,
        string $productId,
        array $data,
        ?string $promotionId = null
    ): ProductPromotion {
        return DB::transaction(function () use ($tenantId, $productId, $data, $promotionId): ProductPromotion {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $product = $tenant->products()->lockForUpdate()->findOrFail($productId);

            if ((float) $data['promotional_price'] > (float) $product->price) {
                throw ValidationException::withMessages([
                    'promotional_price' => 'O preço promocional deve ser menor ou igual ao preço base.',
                ]);
            }

            if ($promotionId !== null) {
                $promotion = $product->promotions()->findOrFail($promotionId);
                $promotion->update($data);
            } else {
                $promotion = $product->promotions()->create($data);
            }

            return $promotion->fresh();
        });
    }
}
