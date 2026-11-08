<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\EvaluateClientTransferAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientSeller;
use App\Models\ClientTransferRequest;
use App\Models\TenantUser;
use App\Enums\TenantUserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientTransferController extends Controller
{
    private function member(Request $request, string $tenantId): TenantUser
    {
        return TenantUser::query()->where('tenant_id', $tenantId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'ACTIVE')->firstOrFail();
    }

    public function lookup(Request $request, string $tenantId, EvaluateClientTransferAction $eligibility): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('createClients', $tenant), 403);
        $member = $this->member($request, $tenant->id);
        $data = $request->validate(['document' => ['required', 'string', 'regex:/^\d{11}$|^\d{14}$/']]);

        $client = Client::query()->where('tenant_id', $tenant->id)
            ->where('document', $data['document'])->first();

        if (! $client) {
            return response()->json(['data' => ['exists' => false]]);
        }

        $owner = ClientSeller::query()->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)->whereNull('ended_at')->with('membership')->first();

        return response()->json(['data' => [
            'exists' => true,
            'client_id' => $client->id,
            'assigned_to_me' => $owner?->tenant_user_id === $member->id,
            'owner_name' => $owner?->membership?->user?->name,
            'transfer' => $eligibility->execute($client),
        ]]);
    }

    public function store(Request $request, string $tenantId, EvaluateClientTransferAction $eligibility): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('createClients', $tenant), 403);
        $member = $this->member($request, $tenant->id);
        $data = $request->validate([
            'client_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $transfer = DB::transaction(function () use ($tenant, $member, $data, $eligibility) {
            $client = Client::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($data['client_id']);
            $owner = ClientSeller::query()->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)->whereNull('ended_at')
                ->lockForUpdate()->firstOrFail();

            if ($owner->tenant_user_id === $member->id) {
                throw ValidationException::withMessages(['client_id' => 'O cliente já pertence à sua carteira.']);
            }

            if (! $eligibility->execute($client)['eligible']) {
                throw ValidationException::withMessages(['client_id' => 'Cliente não atende aos critérios de transferência.']);
            }

            if (ClientTransferRequest::query()->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)->where('status', 'PENDING')->exists()) {
                throw ValidationException::withMessages(['client_id' => 'Já existe uma transferência pendente para este cliente.']);
            }

            return ClientTransferRequest::query()->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'from_tenant_user_id' => $owner->tenant_user_id,
                'to_tenant_user_id' => $member->id,
                'requested_by' => $member->id,
                'status' => 'PENDING',
                'reason' => $data['reason'] ?? null,
            ]);
        });

        return response()->json(['data' => ['id' => $transfer->id, 'status' => $transfer->status]], 201);
    }

    public function index(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $member = $this->member($request, $tenant->id);
        $query = ClientTransferRequest::query()->where('tenant_id', $tenant->id)
            ->where('status', 'PENDING')->with(['client:id,company_name', 'source', 'destination']);

        if (! in_array($member->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $teams = $member->supervisedTeams()->where('tenant_id', $tenant->id)->select('id');
            $query->whereHas('source', fn ($source) => $source->whereIn('team_id', $teams));
        }

        return response()->json(['data' => $query->orderBy('created_at')->get()->map(fn ($item) => [
            'id' => $item->id,
            'client_name' => $item->client?->company_name,
            'from_name' => $item->source?->user?->name,
            'to_name' => $item->destination?->user?->name,
            'reason' => $item->reason,
            'created_at' => $item->created_at,
        ])]);
    }

    public function review(Request $request, string $tenantId, string $transferId, EvaluateClientTransferAction $eligibility): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $reviewer = $this->member($request, $tenant->id);
        $data = $request->validate([
            'decision' => ['required', 'in:APPROVED,REJECTED'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($tenant, $reviewer, $transferId, $data, $eligibility): void {
            $transfer = ClientTransferRequest::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($transferId);
            abort_unless($transfer->status === 'PENDING', 409);

            $source = TenantUser::query()->where('tenant_id', $tenant->id)
                ->findOrFail($transfer->from_tenant_user_id);
            $isManager = in_array($reviewer->role, [TenantUserRole::Owner, TenantUserRole::Manager], true);
            $supervises = $reviewer->supervisedTeams()->where('tenant_id', $tenant->id)
                ->whereKey($source->team_id)->exists();
            abort_unless($isManager || $supervises, 403);

            $client = Client::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($transfer->client_id);
            $owner = ClientSeller::query()->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)->whereNull('ended_at')
                ->lockForUpdate()->firstOrFail();
            abort_unless($owner->tenant_user_id === $transfer->from_tenant_user_id, 409);

            if ($data['decision'] === 'APPROVED') {
                if (! $eligibility->execute($client)['eligible']) {
                    throw ValidationException::withMessages(['decision' => 'O cliente não atende mais aos critérios de transferência.']);
                }
                $destination = TenantUser::query()->where('tenant_id', $tenant->id)
                    ->where('status', 'ACTIVE')->findOrFail($transfer->to_tenant_user_id);

                $owner->update(['ended_at' => now(), 'reason' => 'Transferência autorizada: ' . $transfer->id]);
                ClientSeller::query()->create([
                    'tenant_id' => $tenant->id,
                    'client_id' => $client->id,
                    'tenant_user_id' => $destination->id,
                    'assigned_by' => $requestUserId = $reviewer->user_id,
                    'started_at' => now(),
                    'reason' => 'Transferência autorizada: ' . $transfer->id,
                ]);
            }

            $transfer->update([
                'status' => $data['decision'],
                'reviewed_by' => $reviewer->id,
                'review_note' => $data['review_note'] ?? null,
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['data' => ['status' => $data['decision']]]);
    }
}
