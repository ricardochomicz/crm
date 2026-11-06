<?php

namespace App\Http\Controllers\Commercial;

use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\CommercialOrder;
use App\Models\CommercialParticipant;
use App\Models\Product;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommercialOrderController extends Controller
{
    private function tenant(Request $request, bool $write = false)
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can($write ? 'manageCommissions' : 'viewCommissions', $tenant), 403);
        return $tenant;
    }

    public function index(Request $request, string $tenantId): array
    {
        $tenant = $this->tenant($request);
        return [
            'data' => CommercialOrder::where('tenant_id', $tenant->id)
                ->with(['operator:id,name', 'product:id,name', 'participants'])
                ->orderByDesc('sold_on')->orderByDesc('created_at')->limit(200)->get(),
            'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)],
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

    public function store(Request $request, string $tenantId): array { return $this->save($request); }
    public function update(Request $request, string $tenantId, string $orderId): array { return $this->save($request, $orderId); }

    private function save(Request $request, ?string $orderId = null): array
    {
        $tenant = $this->tenant($request, true);
        $data = $request->validate([
            'operator_id' => ['required', 'integer', Rule::exists('tenant_operators', 'operator_id')->where('tenant_id', $tenant->id)],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('tenant_id', $tenant->id)],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_document' => ['nullable', 'string', 'regex:/^(\\d{11}|\\d{14})$/'],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'line_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'sold_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'participants' => ['required', 'array', 'min:1', 'max:5'],
            'participants.*.role' => ['required', Rule::in(['CONSULTANT', 'SUPERVISOR', 'MANAGER', 'PARTNER', 'REFERRER'])],
            'participants.*.tenant_user_id' => ['nullable', 'uuid'],
            'participants.*.commercial_participant_id' => ['nullable', 'uuid'],
        ]);
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($data['product_id']);
        if ((int) $product->operator_id !== (int) $data['operator_id']) {
            throw ValidationException::withMessages(['product_id' => 'O produto não pertence à operadora selecionada.']);
        }
        $participants = $data['participants'];
        unset($data['participants']);
        $roles = array_column($participants, 'role');
        if (count($roles) !== count(array_unique($roles))) {
            throw ValidationException::withMessages(['participants' => 'Cada papel pode aparecer somente uma vez no pedido.']);
        }
        $resolved = [];
        foreach ($participants as $participant) {
            $role = $participant['role'];
            $internal = in_array($role, ['CONSULTANT', 'SUPERVISOR', 'MANAGER'], true);
            if ($internal) {
                if (empty($participant['tenant_user_id']) || !empty($participant['commercial_participant_id'])) {
                    throw ValidationException::withMessages(['participants' => 'Selecione um membro interno para consultor, supervisor ou gerente.']);
                }
                $member = TenantUser::where('tenant_id', $tenant->id)->where('status', 'ACTIVE')
                    ->with('user:id,name,email')->findOrFail($participant['tenant_user_id']);
                $resolved[] = ['role' => $role, 'tenant_user_id' => $member->id, 'commercial_participant_id' => null, 'participant_name' => $member->user?->name ?? $member->user?->email ?? $member->id];
            } else {
                if (empty($participant['commercial_participant_id']) || !empty($participant['tenant_user_id'])) {
                    throw ValidationException::withMessages(['participants' => 'Selecione um parceiro ou indicador externo.']);
                }
                $external = CommercialParticipant::where('tenant_id', $tenant->id)->where('is_active', true)
                    ->where('role', $role)->findOrFail($participant['commercial_participant_id']);
                $resolved[] = ['role' => $role, 'tenant_user_id' => null, 'commercial_participant_id' => $external->id, 'participant_name' => $external->name];
            }
        }
        return DB::transaction(function () use ($tenant, $orderId, $data, $resolved) {
            $order = $orderId
                ? CommercialOrder::where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($orderId)
                : new CommercialOrder(['tenant_id' => $tenant->id]);
            abort_unless($order->status === null || $order->status === 'DRAFT', 422, 'Somente pedidos em rascunho podem ser editados.');
            $order->fill($data);
            $order->status = 'DRAFT';
            $order->save();
            $order->participants()->delete();
            $order->participants()->createMany($resolved);
            return ['data' => $order->fresh(['operator:id,name', 'product:id,name', 'participants'])];
        });
    }
}
