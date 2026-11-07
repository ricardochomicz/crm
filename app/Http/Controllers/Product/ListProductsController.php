<?php

namespace App\Http\Controllers\Product;

use App\Actions\Product\FilterProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListProductsController extends Controller
{
    public function __invoke(
        IndexProductRequest $request,
        FilterProductsAction $action
    ): AnonymousResourceCollection {
        $tenant = $request->attributes->get('active_tenant');
        $user = $request->user();

        return ProductResource::collection(
            $action->execute($tenant->id, $request->validated())
        )->additional([
            'meta' => [
                'can_manage' => $user->can('createProducts', $tenant)
                    || $user->can('updateProducts', $tenant)
                    || $user->can('managePromotions', $tenant),
                'can_create' => $user->can('createProducts', $tenant),
                'can_update' => $user->can('updateProducts', $tenant),
                'can_manage_promotions' => $user->can('managePromotions', $tenant),
            ],
        ]);
    }
}
