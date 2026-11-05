<?php
namespace App\Http\Controllers\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SaveProductRequest;
use Illuminate\Http\JsonResponse;
class StoreProductController extends Controller
{
    public function __invoke(SaveProductRequest $request): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        return response()->json(['data' => $tenant->products()->create($request->validated())], 201);
    }
}
