<?php
namespace App\Http\Controllers\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SaveProductRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Product\ProductResource;
class StoreProductController extends Controller
{
    public function __invoke(SaveProductRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        return (new ProductResource($tenant->products()->create($request->validated())))->response()->setStatusCode(201);
    }
}
