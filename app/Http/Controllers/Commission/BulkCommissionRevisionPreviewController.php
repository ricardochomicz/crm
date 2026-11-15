<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterOrderCommissionsAction;
use App\Actions\Commission\PreviewBulkCommissionRevisionAction;
use App\Http\Controllers\Controller;
use App\Rules\ValidCommissionOrderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BulkCommissionRevisionPreviewController extends Controller
{
    public function __invoke(Request $request, FilterOrderCommissionsAction $filter, PreviewBulkCommissionRevisionAction $preview): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant !== null && $request->user()->can('manageCommissions', $tenant)
            && $request->user()->can('viewOrders', $tenant), 403);

        $data = Validator::make($request->all(), [
            'order_ids' => ['required', 'array', 'min:1', 'max:20'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', 'string', new ValidCommissionOrderType],
        ])->validate();

        $rows = [];
        foreach ($data['order_ids'] as $orderId) {
            $order = $filter->findOrder($tenant, $request->user(), $orderId);
            $rows[] = $preview->execute($order, (int) $data['operator_id'], $data['order_type']);
        }

        return response()->json(['data' => ['orders' => $rows, 'message' => 'Prévia informativa, sem alteração financeira. Os valores serão validados novamente ao confirmar.']]);
    }
}
