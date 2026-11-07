<?php

namespace App\Http\Controllers\Product;

use App\Actions\Product\SaveProductPromotionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SavePromotionRequest;
use App\Http\Resources\Product\ProductPromotionResource;
use Illuminate\Http\JsonResponse;

class UpdatePromotionController extends Controller
{
    public function __invoke(
        SavePromotionRequest $request,
        string $tenantId,
        string $productId,
        string $promotionId,
        SaveProductPromotionAction $action
    ): JsonResponse {
        $promotion = $action->execute(
            $request->attributes->get('active_tenant')->id,
            $productId,
            $request->validated(),
            $promotionId
        );

        return (new ProductPromotionResource($promotion))->response();
    }
}
