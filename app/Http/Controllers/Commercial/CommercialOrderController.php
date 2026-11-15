<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\FilterCommercialOrderAction;
use App\Actions\Commercial\ScopeVisibleCommercialOrdersAction;
use App\Actions\Commercial\OrderActivationOverviewAction;
use App\Actions\Commercial\PendingOrderActivationOverviewAction;
use App\Actions\Commercial\ResolveOrderParticipantsAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\IndexCommercialOrderRequest;
use App\Http\Requests\Commercial\SaveCommercialOrderRequest;
use App\Http\Resources\Commercial\CommercialOrderResource;
use App\Models\CommercialOrder;
use App\Models\CommercialParticipant;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialOrderController extends Controller
{
    public function __construct(private ResolveOrderParticipantsAction $participants, private ScopeVisibleCommercialOrdersAction $scope) {}

    private function tenant(Request $request, bool $write = false): Tenant
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can($write ? 'manageOrders' : 'viewOrders', $tenant), 403);

        return $tenant;
    }

    public function index(IndexCommercialOrderRequest $request, string $tenantId, FilterCommercialOrderAction $filter): array
    {
        $tenant = $request->attributes->get('active_tenant');
        $visibleIds = $this->scope->membershipIds($tenant, $request->user());

        return [
            'data' => CommercialOrderResource::collection($filter->execute($tenant->id, $request->validated(), $visibleIds)),
            'meta' => ['can_manage' => $request->user()->can('manageOrders', $tenant)],
        ];
    }

    public function activationOverview(Request $request, string $tenantId, OrderActivationOverviewAction $action): array
    {
        $tenant = $this->tenant($request);
        $filters = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'order_type_ids' => ['sometimes', 'array', 'max:50'],
            'order_type_ids.*' => ['required', 'uuid', 'distinct', 'exists:order_types,id'],
        ]);

        return ['data' => $action->execute(
            $tenant,
            $request->user(),
            $filters['date_from'],
            $filters['date_to'],
            $filters['order_type_ids'] ?? [],
        )];
    }

    public function pendingActivationOverview(Request $request, string $tenantId, PendingOrderActivationOverviewAction $action): array
    {
        $tenant = $this->tenant($request);

        return ['data' => $action->execute($tenant, $request->user(), now()->toDateString())];
    }

    public function options(Request $request, string $tenantId): array
    {
        $tenant = $this->tenant($request);

        // The order form needs participant options, but orders.view alone must
        // not expose the complete company member/partner directory.
        $canManage = $request->user()->can('manageOrders', $tenant);
        $member = $request->user()->can('viewMembers', $tenant);
        $actorMembership = app(ResolveTenantPermissionsAction::class)
            ->membership($request->user(), $tenant) ?? abort(403);

        return ['data' => [
            'members' => TenantUser::where('tenant_id', $tenant->id)->where('status', 'ACTIVE')
                ->when(! $canManage && ! $member, fn ($query) => $query->whereKey($actorMembership->id))
                ->with('user:id,name,email')->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name ?? $m->user?->email ?? $m->id])->values(),
            'externals' => $canManage
                ? CommercialParticipant::where('tenant_id', $tenant->id)->where('is_active', true)
                    ->orderBy('name')->get(['id', 'name', 'role'])
                : collect(),
        ]];
    }

    public function store(SaveCommercialOrderRequest $request, string $tenantId): array
    {
        return $this->save($request);
    }

    public function update(SaveCommercialOrderRequest $request, string $tenantId, string $orderId): array
    {
        return $this->save($request, $orderId);
    }

    private function save(SaveCommercialOrderRequest $request, ?string $orderId = null): array
    {
        $tenant = $this->tenant($request, true);
        $data = $request->validated();
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($data['product_id']);
        if ((int) $product->operator_id !== (int) $data['operator_id']) {
            throw ValidationException::withMessages(['product_id' => 'O produto não pertence à operadora selecionada.']);
        }
        $participants = $data['participants'];
        unset($data['participants']);
        $resolved = $this->participants->execute($tenant->id, $participants);

        // A non-manager must not create an order assigned outside the same
        // visibility scope used for subsequent reads and workflow changes.
        $visibleIds = $this->scope->membershipIds($tenant, $request->user());
        $consultantId = collect($resolved)->firstWhere('role', 'CONSULTANT')['tenant_user_id'] ?? null;
        if ($visibleIds !== null && ($consultantId === null || ! in_array($consultantId, $visibleIds, true))) {
            throw ValidationException::withMessages([
                'participants' => 'Selecione um consultor da sua carteira ou equipe para este pedido.',
            ]);
        }
        if ($orderId === null && $consultantId !== null) {
            $data['tenant_user_id'] = $consultantId;
        }

        return DB::transaction(function () use ($tenant, $orderId, $data, $resolved, $request) {
            $order = $orderId
                ? $this->scope->apply(CommercialOrder::query(), $tenant, $request->user())->lockForUpdate()->findOrFail($orderId)
                : new CommercialOrder(['tenant_id' => $tenant->id]);
            abort_unless($order->status === null || $order->status === 'DRAFT', 422, 'Somente pedidos em rascunho podem ser editados.');
            if ($order->exists && (
                $order->administratively_completed_at !== null
                || $order->activated_at !== null
                || $order->order_status_id !== null
                || $order->opportunity_id !== null
                || $order->items()->exists()
            )) {
                throw ValidationException::withMessages([
                    'order' => 'Pedidos no fluxo operacional não podem ser editados como rascunho. Utilize o fluxo de correção da oportunidade.',
                ]);
            }
            $order->fill($data);
            $order->status = 'DRAFT';
            $order->save();
            $order->participants()->delete();
            $order->participants()->createMany($resolved);

            return ['data' => new CommercialOrderResource($order->fresh(['operator:id,name', 'product:id,name', 'participants']))];
        });
    }
}
