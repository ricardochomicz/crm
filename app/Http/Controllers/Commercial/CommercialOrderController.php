<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\FilterCommercialOrderAction;
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
    public function __construct(private ResolveOrderParticipantsAction $participants) {}

    private function tenant(Request $request, bool $write = false): Tenant
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can($write ? 'manageOrders' : 'viewOrders', $tenant), 403);

        return $tenant;
    }

    public function index(IndexCommercialOrderRequest $request, string $tenantId, FilterCommercialOrderAction $filter, ResolveTenantPermissionsAction $permissions): array
    {
        $tenant = $request->attributes->get('active_tenant');
        $actor = $permissions->membership($request->user(), $tenant) ?? abort(403);
        $visibleIds = null;
        if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->pluck('id');
            $visibleIds = TenantUser::query()->where('tenant_id', $tenant->id)
                ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
        }

        return [
            'data' => CommercialOrderResource::collection($filter->execute($tenant->id, $request->validated(), $visibleIds)),
            'meta' => ['can_manage' => $request->user()->can('manageOrders', $tenant)],
        ];
    }

    public function options(Request $request, string $tenantId): array
    {
        $tenant = $this->tenant($request);

        return ['data' => [
            'members' => TenantUser::where('tenant_id', $tenant->id)->where('status', 'ACTIVE')
                ->with('user:id,name,email')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name ?? $m->user?->email ?? $m->id])->values(),
            'externals' => CommercialParticipant::where('tenant_id', $tenant->id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'role']),
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

        return DB::transaction(function () use ($tenant, $orderId, $data, $resolved) {
            $order = $orderId
                ? CommercialOrder::where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($orderId)
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
