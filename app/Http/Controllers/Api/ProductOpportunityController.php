<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductOpportunity;
use Illuminate\Http\Request;

class ProductOpportunityController extends Controller
{

    public function index()
    {
        //
    }


    public function store(Request $request)
    {
//        $size = count(collect($request));
//        for ($p = 0; $p < $size; ++$p):
//            $query = array(
//                'product' => $request->product[$p++],
//                'price' => $request->price[$p++],
//                'qtd' => $request->qtd[$p++],
//            );
//            --$p;
//            $items = ProductOpportunity::create($query);
//            return response()->json($items);
//        endfor;
        foreach ($request as $key => $value){
            $items = ProductOpportunity::create($value);
            return response()->json($items);
        }
        //$items = ProductOpportunity::create($request->all());



    }


    public function show(ProductOpportunity $productOpportunity)
    {
        //
    }


    public function update(Request $request, ProductOpportunity $productOpportunity)
    {
        //
    }


    public function destroy(ProductOpportunity $productOpportunity)
    {
        //
    }
}
