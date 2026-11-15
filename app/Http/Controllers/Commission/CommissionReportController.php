<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\CommissionReportAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionReportController extends Controller
{
    public function __invoke(Request $request, CommissionReportAction $report): JsonResponse
    {
        $filters = Validator::make($request->all(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'in:ESTIMATED,ELIGIBLE,PENDING_RULES'],
        ])->validate();

        return response()->json(['data' => $report->execute(
            $request->attributes->get('active_tenant'),
            $request->user(),
            $filters
        )]);
    }
}
