<?php

namespace App\Http\Controllers\AfterSales;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProtocolController extends Controller
{
    private const CATEGORIES = ['activation', 'portability', 'billing', 'support', 'contract', 'cancellation', 'other'];
    private const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'cancelled', 'reopened'];

    private function actor(Request $request): TenantUser
    {
        $tenant = $request->attributes->get('active_tenant');
        $actor = TenantUser::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();
        abort_unless(in_array($actor->role->value, ['OWNER', 'MANAGER', 'CONSULTANT', 'ASSISTANT'], true), 403);
        return $actor;
    }

    private function visibleClientIds(Request $request, TenantUser $actor)
    {
        $tenant = $request->attributes->get('active_tenant');
        if (in_array($actor->role->value, ['OWNER', 'MANAGER'], true)) {
            return DB::table('clients')->where('tenant_id', $tenant->id)->select('id');
        }
        $memberIds = [$actor->id];
        $teamIds = DB::table('teams')->where('tenant_id', $tenant->id)->where('supervisor_membership_id', $actor->id)->pluck('id');
        if ($teamIds->isNotEmpty()) {
            $memberIds = array_merge($memberIds, DB::table('tenant_users')->where('tenant_id', $tenant->id)->whereIn('team_id', $teamIds)->where('status', 'ACTIVE')->pluck('id')->all());
        }
        return DB::table('client_sellers')->where('tenant_id', $tenant->id)->whereIn('tenant_user_id', $memberIds)->whereNull('ended_at')->select('client_id');
    }

    private function accessible(Request $request, TenantUser $actor, string $id): object
    {
        return DB::table('after_sales_protocols')->where('tenant_id', $request->attributes->get('active_tenant')->id)
            ->whereIn('client_id', $this->visibleClientIds($request, $actor))->where('id', $id)->firstOrFail();
    }

    private function details(Request $request, TenantUser $actor, string $id): JsonResponse
    {
        $protocol = $this->accessible($request, $actor, $id);
        $history = DB::table('after_sales_protocol_history as h')->leftJoin('tenant_users as tu', function ($join) {
            $join->on('tu.id', '=', 'h.tenant_user_id')->on('tu.tenant_id', '=', 'h.tenant_id');
        })->leftJoin('users as u', 'u.id', '=', 'tu.user_id')->where('h.tenant_id', $protocol->tenant_id)->where('h.protocol_id', $id)
            ->orderBy('h.created_at')->get(['h.id', 'h.action', 'h.from_status', 'h.to_status', 'h.note', 'h.created_at', 'u.name as actor_name']);
        return response()->json(['data' => $protocol, 'history' => $history]);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $tenantId = $request->attributes->get('active_tenant')->id;
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(self::STATUSES)], 'category' => ['nullable', Rule::in(self::CATEGORIES)], 'client_id' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = DB::table('after_sales_protocols as p')->join('clients as c', function ($join) {
            $join->on('c.id', '=', 'p.client_id')->on('c.tenant_id', '=', 'p.tenant_id');
        })->leftJoin('tenant_users as assignee', function ($join) {
            $join->on('assignee.id', '=', 'p.assigned_to_tenant_user_id')->on('assignee.tenant_id', '=', 'p.tenant_id');
        })->leftJoin('users as responsible', 'responsible.id', '=', 'assignee.user_id')
            ->where('p.tenant_id', $tenantId)->whereIn('p.client_id', $this->visibleClientIds($request, $actor))
            ->select('p.*', 'c.company_name as client_name', 'responsible.name as assignee_name');
        if (!empty($filters['client_id'])) $query->where('p.client_id', $filters['client_id']);
        if (!empty($filters['status'])) $query->where('p.status', $filters['status']);
        if (!empty($filters['category'])) $query->where('p.category', $filters['category']);
        if (!empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('p.code', 'like', $term)->orWhere('p.title', 'like', $term)->orWhere('c.company_name', 'like', $term));
        }
        return response()->json($query->orderByDesc('p.created_at')->paginate(15));
    }

    public function options(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $tenantId = $request->attributes->get('active_tenant')->id;
        $clients = DB::table('clients')->where('tenant_id', $tenantId)->whereIn('id', $this->visibleClientIds($request, $actor))->orderBy('company_name')->get(['id', 'company_name']);
        $members = DB::table('tenant_users as tu')->join('users as u', 'u.id', '=', 'tu.user_id')
            ->where('tu.tenant_id', $tenantId)->where('tu.status', 'ACTIVE')->orderBy('u.name')->get(['tu.id', 'u.name']);
        return response()->json(['data' => ['clients' => $clients, 'members' => $members, 'current_member_id' => $actor->id]]);
    }

    public function show(Request $request, string $tenantId, string $protocolId): JsonResponse
    {
        return $this->details($request, $this->actor($request), $protocolId);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $tenantId = $request->attributes->get('active_tenant')->id;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('after_sales_protocols', 'code')->where('tenant_id', $tenantId)],
            'client_id' => ['required', 'uuid'],
            'commercial_order_id' => ['nullable', 'uuid'],
            'assigned_to_tenant_user_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
        ]);
        abort_unless(DB::table('clients')->where('tenant_id', $tenantId)->where('id', $data['client_id'])->whereIn('id', $this->visibleClientIds($request, $actor))->exists(), 403);
        if (!empty($data['commercial_order_id'])) {
            abort_unless(DB::table('commercial_orders')->where('tenant_id', $tenantId)->where('client_id', $data['client_id'])->where('id', $data['commercial_order_id'])->exists(), 422, 'Pedido não pertence ao cliente.');
        }
        $data['code'] = trim($data['code']);
        $data['assigned_to_tenant_user_id'] = $data['assigned_to_tenant_user_id'] ?? $actor->id;
        $this->validateAssignee($tenantId, $data['assigned_to_tenant_user_id']);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($tenantId, $actor, $data, $id): void {
            DB::table('after_sales_protocols')->insert([...$data, 'id' => $id, 'tenant_id' => $tenantId, 'opened_by_tenant_user_id' => $actor->id, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
            $this->history($tenantId, $id, $actor->id, 'created', null, 'open', $data['description']);
        });
        return $this->details($request, $actor, $id)->setStatusCode(201);
    }

    private function validateAssignee(string $tenantId, ?string $id): void
    {
        if ($id !== null) abort_unless(DB::table('tenant_users')->where('tenant_id', $tenantId)->where('id', $id)->where('status', 'ACTIVE')->exists(), 422, 'Responsável inválido.');
    }

    private function history(string $tenantId, string $id, string $actorId, string $action, ?string $from, ?string $to, ?string $note): void
    {
        DB::table('after_sales_protocol_history')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'protocol_id' => $id, 'tenant_user_id' => $actorId, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'note' => $note, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function update(Request $request, string $tenantId, string $protocolId): JsonResponse
    {
        $actor = $this->actor($request);
        $protocol = $this->accessible($request, $actor, $protocolId);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'assigned_to_tenant_user_id' => ['sometimes', 'nullable', 'uuid'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'note' => ['nullable', 'string', 'max:10000'],
        ]);
        $note = $data['note'] ?? null;
        unset($data['note']);
        if (array_key_exists('assigned_to_tenant_user_id', $data)) $this->validateAssignee($protocol->tenant_id, $data['assigned_to_tenant_user_id']);
        if (isset($data['status']) && in_array($data['status'], ['resolved', 'cancelled'], true) && ! $note) {
            return response()->json(['message' => 'Informe a resolução ou o motivo do cancelamento.'], 422);
        }
        $previous = $protocol->status;
        if (isset($data['status']) && $data['status'] === 'resolved') $data['resolved_at'] = now();
        elseif (isset($data['status']) && $data['status'] !== 'resolved') $data['resolved_at'] = null;
        DB::transaction(function () use ($protocol, $data, $note, $actor, $previous): void {
            if ($data) DB::table('after_sales_protocols')->where('tenant_id', $protocol->tenant_id)->where('id', $protocol->id)->update([...$data, 'updated_at' => now()]);
            $this->history($protocol->tenant_id, $protocol->id, $actor->id, 'updated', $previous, $data['status'] ?? $previous, $note);
        });
        return $this->details($request, $actor, $protocolId);
    }
}
