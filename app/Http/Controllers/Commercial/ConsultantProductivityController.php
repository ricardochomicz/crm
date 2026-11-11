<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\ConsultantProductivityAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultantProductivityController extends Controller
{
    public function __invoke(Request $request, string $tenantId, ConsultantProductivityAction $action): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'team_id' => ['sometimes', 'uuid'],
            'consultant_id' => ['sometimes', 'uuid'],
        ]);
        abort_if(\Carbon\Carbon::parse($data['from'])->diffInDays(\Carbon\Carbon::parse($data['to'])) > 366, 422, 'O período máximo é de 366 dias.');
        $tenant = $request->attributes->get('active_tenant');
        return response()->json($action->overview($tenant, $request->user(), $data));
    }
}
