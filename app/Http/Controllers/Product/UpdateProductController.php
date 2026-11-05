<?php
namespace App\Http\Controllers\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SaveProductRequest;
use Illuminate\Http\JsonResponse;
class UpdateProductController extends Controller
{
    public function __invoke(SaveProductRequest $request, string $tenantId, string $productId): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $product = $tenant->products()->findOrFail($productId);
        $product->update($request->validated());
        return response()->json(['data' => $product->fresh()]);
    }
}
