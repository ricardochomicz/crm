<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SavePromotionRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class UpdatePromotionController extends Controller
{
    public function __invoke(SavePromotionRequest $request, string $tenantId, string $productId, string $promotionId): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        $product = $tenant->products()->findOrFail($productId);
        $promotion = $product->promotions()->findOrFail($promotionId);
        $data = $request->validated();
        abort_if((float) $data['promotional_price'] > (float) $product->price, 422, 'O preço promocional deve ser menor ou igual ao preço base.');
        $promotion->update($data);

        return response()->json(['data' => $promotion->fresh()]);
    }
}
