<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\CalculateSaleCommissionAction;
use App\Actions\Commission\CaptureOrderCommissionsAction;
use App\Actions\Commission\FilterOrderCommissionsAction;
use App\Actions\Commission\PreviewBulkCommissionRevisionAction;
use App\Actions\Commission\ResolveOrderCommissionRulesAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Http\Controllers\Controller;
use App\Models\CommercialOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class BulkCommissionRevisionController extends Controller
{
    public function __invoke(
        Request $request,
        FilterOrderCommissionsAction $filter,
        ResolveOrderCommissionRulesAction $rules,
        CalculateSaleCommissionAction $calculator,
        CaptureOrderCommissionsAction $capture,
        PreviewBulkCommissionRevisionAction $preview,
        ResolveTenantPermissionsAction $permissions,
    ): JsonResponse {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant !== null && $request->user()->can('manageCommissions', $tenant)
            && $request->user()->can('viewOrders', $tenant), 403);

        $data = Validator::make($request->all(), [
            'order_ids' => ['required', 'array', 'min:1', 'max:20'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', 'string', new \App\Rules\ValidCommissionOrderType],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ])->validate();

        $actor = $permissions->membership($request->user(), $tenant) ?? abort(403);
        $results = [];
        foreach ($data['order_ids'] as $orderId) {
            try {
                $results[] = DB::transaction(function () use ($tenant, $request, $orderId, $data, $filter, $rules, $calculator, $capture, $preview, $actor): array {
                    $order = $filter->findOrder($tenant, $request->user(), $orderId);
                    $preflight = $preview->execute($order, (int) $data['operator_id'], $data['order_type']);
                    if ($preflight['status'] !== 'ready') {
                        return ['order_id' => $orderId, 'status' => 'skipped', 'reason' => $preflight['reason']];
                    }
                    $items = $order->items()->get();
                    $pending = DB::table('order_commission_accruals')
                        ->where('tenant_id', $tenant->id)->where('commercial_order_id', $order->id)
                        ->where('operator_id', $data['operator_id'])->where('order_type', $data['order_type'])
                        ->where('is_current', true)->where('status', 'PENDING_RULES')->exists();
                    if (! $pending) {
                        return ['order_id' => $orderId, 'status' => 'skipped', 'reason' => 'no_matching_pending_commission'];
                    }

                    $snapshots = $rules->execute($order, $items, $order->sold_on->toDateString());
                    foreach ($items as $item) {
                        $key = $rules->key((int) ($item->operator_id ?? $order->operator_id), $rules->orderType($order, $item));
                        $snapshot = $snapshots[$key] ?? null;
                        if ($snapshot === null || ($snapshot['issues'] ?? []) !== [] || $item->quantity !== 1) {
                            return ['order_id' => $orderId, 'status' => 'skipped', 'reason' => 'unresolved_rules_or_nonindividualized_lines'];
                        }
                        $calculation = $calculator->execute(
                            (string) $item->monthly_unit_amount,
                            (string) $snapshot['company_percentage'],
                            (string) $snapshot['deduction_percentage'],
                            $snapshot['participants'],
                        );
                        if ($calculation['issues'] !== []) {
                            return ['order_id' => $orderId, 'status' => 'skipped', 'reason' => 'invalid_commission_balance'];
                        }
                    }

                    $revision = $capture->execute($order, $actor->id, 'manual_review', $data['reason']);
                    return ['order_id' => $orderId, 'status' => 'revised', 'revision' => $revision];
                });
            } catch (Throwable $exception) {
                report($exception);
                $results[] = ['order_id' => $orderId, 'status' => 'failed', 'reason' => 'revision_failed'];
            }
        }

        return response()->json(['data' => [
            'results' => $results,
            'revised' => count(array_filter($results, fn ($result) => $result['status'] === 'revised')),
            'skipped' => count(array_filter($results, fn ($result) => $result['status'] === 'skipped')),
            'failed' => count(array_filter($results, fn ($result) => $result['status'] === 'failed')),
        ]]);
    }
}
