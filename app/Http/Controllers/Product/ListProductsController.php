<?php
namespace App\Http\Controllers\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class ListProductsController extends Controller
{
    public function __invoke(Request $request): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewProducts', $tenant), 403);
        $filters = $request->validate(['operator_id' => ['sometimes', 'integer'], 'category' => ['sometimes', 'string']]);
        $query = $tenant->products()->with(['operator:id,name', 'promotions'])->orderBy('name');
        if (isset($filters['operator_id'])) $query->where('operator_id', $filters['operator_id']);
        if (isset($filters['category'])) $query->where('category', $filters['category']);
        return ['data' => $query->get(), 'meta' => ['can_manage' => $request->user()->can('createProducts', $tenant) || $request->user()->can('updateProducts', $tenant) || $request->user()->can('managePromotions', $tenant), 'can_create' => $request->user()->can('createProducts', $tenant), 'can_update' => $request->user()->can('updateProducts', $tenant), 'can_manage_promotions' => $request->user()->can('managePromotions', $tenant)]];
    }
}
