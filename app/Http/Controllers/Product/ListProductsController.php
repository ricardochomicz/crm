<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Queries\Product\ProductIndexQuery;

class ListProductsController extends Controller
{
    public function __invoke(IndexProductRequest $request, ProductIndexQuery $query)
    {
        $tenant = $request->attributes->get('active_tenant');
        $user = $request->user();

        return ProductResource::collection($query->get($tenant->id, $request->validated()))
            ->additional(['meta' => [
                'can_manage' => $user->can('createProducts', $tenant) || $user->can('updateProducts', $tenant) || $user->can('managePromotions', $tenant),
                'can_create' => $user->can('createProducts', $tenant),
                'can_update' => $user->can('updateProducts', $tenant),
                'can_manage_promotions' => $user->can('managePromotions', $tenant),
            ]]);
    }
}
