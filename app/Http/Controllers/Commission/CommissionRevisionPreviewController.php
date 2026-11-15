<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\PreviewCommissionRevisionsAction;
use App\Http\Controllers\Controller;
use App\Rules\ValidCommissionOrderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CommissionRevisionPreviewController extends Controller
{
    public function __invoke(Request $request, PreviewCommissionRevisionsAction $preview): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant !== null && $request->user()->can('manageCommissions', $tenant)
            && $request->user()->can('viewOrders', $tenant), 403);

        $filters = Validator::make($request->all(), [
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', 'string', new ValidCommissionOrderType],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ])->validate();

        return response()->json(['data' => $preview->execute(
            $tenant,
            (int) $filters['operator_id'],
            $filters['order_type'],
            $filters['from'] ?? null,
            $filters['to'] ?? null,
        )]);
    }
}
