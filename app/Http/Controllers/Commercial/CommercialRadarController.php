<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\BuildCommercialRadarAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommercialRadarController extends Controller
{
    public function __invoke(
        Request $request,
        string $tenantId,
        BuildCommercialRadarAction $radar,
    ): JsonResponse {
        $tenant = $request->attributes->get('active_tenant');

        abort_unless($tenant instanceof Tenant && $tenant->id === $tenantId, 403);

        $filters = $request->validate([
            'member_id' => ['sometimes', 'nullable', 'uuid'],
            'scope' => ['sometimes', 'in:mine,team'],
        ]);

        $team = ($filters['scope'] ?? 'mine') === 'team';
        abort_if($team && ! empty($filters['member_id']), 422, 'Selecione uma equipe ou um consultor, não ambos.');

        return response()->json([
            'data' => $radar->execute($tenant, $request->user(), $filters['member_id'] ?? null, $team),
        ]);
    }
}
