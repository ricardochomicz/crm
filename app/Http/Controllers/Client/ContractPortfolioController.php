<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\ListContractPortfolioAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractPortfolioController extends Controller
{
    public function __invoke(Request $request, string $tenantId, ListContractPortfolioAction $action): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewClients', $tenant), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'operator_id' => ['nullable', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $paginator = $action->execute($tenant->id, $request->user()->id, $filters);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
