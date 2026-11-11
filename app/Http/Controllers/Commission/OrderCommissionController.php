<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\CaptureOrderCommissionsAction;
use App\Actions\Commission\FilterOrderCommissionsAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\CaptureOrderCommissionRequest;
use App\Http\Requests\Commission\IndexOrderCommissionRequest;
use App\Http\Resources\Commission\OrderCommissionResource;
use App\Models\CommercialParticipant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderCommissionController extends Controller
{
    public function options(Request $request, string $tenantId, string $orderId, FilterOrderCommissionsAction $filter): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('manageCommissions', $tenant), 403);
        $order = $filter->findOrder($tenant, $request->user(), $orderId);
        $participants = $order->participants()->get(['role', 'tenant_user_id', 'commercial_participant_id', 'participant_name'])->toArray();
        if ($participants === [] && $order->tenant_user_id !== null) {
            $order->loadMissing('consultant.user');
            $participants[] = ['role' => 'CONSULTANT', 'tenant_user_id' => $order->tenant_user_id, 'commercial_participant_id' => null, 'participant_name' => $order->consultant?->user?->name];
        }

        return response()->json(['data' => [
            'consultant_id' => $order->tenant_user_id,
            'participants' => $participants,
            'members' => TenantUser::query()->where('tenant_id', $tenant->id)->where('status', 'ACTIVE')
                ->with('user:id,name')->get()->map(fn (TenantUser $member): array => ['id' => $member->id, 'name' => $member->user?->name ?? $member->id])->values(),
            'externals' => CommercialParticipant::query()->where('tenant_id', $tenant->id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'role']),
        ]]);
    }

    public function index(IndexOrderCommissionRequest $request, string $tenantId, string $orderId, FilterOrderCommissionsAction $filter): AnonymousResourceCollection
    {
        $order = $filter->findOrder($request->attributes->get('active_tenant'), $request->user(), $orderId);
        $filters = $request->validated();
        $query = $filter->query($order, $filters);
        $currentRevision = $filter->query($order, [])->max('revision');
        $summary = (clone $query)->selectRaw('status, COUNT(*) as item_count, SUM(quantity) as line_count, SUM(base_amount) as base_amount, SUM(gross_amount) as gross_amount, SUM(deduction_amount) as deduction_amount, SUM(net_amount) as net_amount, SUM(participant_total) as participant_total, SUM(company_balance) as company_balance')
            ->groupBy('status')->get();

        return OrderCommissionResource::collection(
            $query->orderBy('source_item_id')->paginate($filters['per_page'] ?? 20)
        )->additional(['meta' => ['current_revision' => $currentRevision === null ? null : (int) $currentRevision, 'requires_capture' => $currentRevision === null, 'summary' => $summary]]);
    }

    public function store(CaptureOrderCommissionRequest $request, string $tenantId, string $orderId, FilterOrderCommissionsAction $filter, CaptureOrderCommissionsAction $capture, ResolveTenantPermissionsAction $permissions): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $order = $filter->findOrder($tenant, $request->user(), $orderId);
        $actor = $permissions->membership($request->user(), $tenant) ?? abort(403);
        $revision = $capture->execute($order, $actor->id, 'manual_review', $request->validated('reason'), $request->validated('participants'));

        return response()->json(['data' => ['order_id' => $order->id, 'revision' => $revision]], 201);
    }
}
