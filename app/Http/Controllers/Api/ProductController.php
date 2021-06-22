<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{

    public function index()
    {
        return Product::orderBy('active', 'DESC')->orderBy('name', 'ASC')->get();
    }


    public function store(Request $request)
    {
        $product = Product::create($request->all());
        $product->refresh();
        return new ProductResource($product);
    }


    public function show(Product $product)
    {
        return new ProductResource($product);
    }



    public function update(Request $request, Product $product)
    {
        $product->fill($request->all());
        $product->save();
        return new ProductResource($product);
    }


    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json([], 204);
    }

    public function getProduct($value)
    {
        $product = Product::where('name', 'LIKE', "%$value%")
            ->get();
        return response()->json($product);

    }
}
