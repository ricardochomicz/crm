<?php

namespace App\Http\Controllers\Search;

use App\Actions\Search\GlobalSearchAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, string $tenantId, GlobalSearchAction $search): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant && $tenant->id === $tenantId, 403);

        $term = trim((string) $request->query('q', ''));
        $validated = validator(['q' => $term], [
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ])->validate();

        return response()->json(['data' => $search->execute($tenant, $request->user(), $validated['q'])]);
    }
}
