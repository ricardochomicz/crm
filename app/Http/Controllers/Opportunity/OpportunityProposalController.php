<?php

namespace App\Http\Controllers\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityProposal;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpportunityProposalController extends Controller
{
    private function tenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant, 403);
        return $tenant;
    }

    public function index(Request $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()->can('viewOpportunities', $tenant), 403);
        $action->find($tenant, $request->user(), $opportunityId);
        $proposals = OpportunityProposal::query()->where('tenant_id', $tenant->id)
            ->where('opportunity_id', $opportunityId)->orderByDesc('version')->get();
        return response()->json(['data' => $proposals->map(fn ($proposal) => $this->present($proposal))]);
    }

    public function store(Request $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()->can('updateOpportunities', $tenant), 403);
        $action->find($tenant, $request->user(), $opportunityId);
        $data = $request->validate([
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'conditions' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $proposal = DB::transaction(function () use ($tenant, $opportunityId, $data) {
            $opportunity = Opportunity::query()->where('tenant_id', $tenant->id)
                ->whereKey($opportunityId)->lockForUpdate()->firstOrFail();
            $opportunity->load(['client', 'operator', 'assignee.user', 'items', 'client.contacts']);
            abort_unless($opportunity->items->isNotEmpty(), 422, 'Adicione serviços antes de gerar a proposta.');
            $version = (int) OpportunityProposal::query()->where('opportunity_id', $opportunityId)->max('version') + 1;
            $contact = $opportunity->client?->contacts
                ->where('is_active', true)->sortByDesc('is_primary')->first();
            $snapshot = [
                'title' => 'Proposta Comercial '.($opportunity->operator?->name ?? 'Operadora').' Empresas',
                'company_name' => $tenant->name,
                'client_name' => $opportunity->client?->company_name ?? 'Cliente',
                'client_contact_name' => $contact?->name,
                'consultant_phone' => $opportunity->assignee?->mobile_phone ?: $opportunity->assignee?->secondary_phone,
                'consultant_name' => $opportunity->assignee?->user?->name ?? 'Não informado',
                'opportunity_code' => $opportunity->code,
                'conditions' => $data['conditions'],
                'notes' => $data['notes'] ?? '',
                'items' => $opportunity->items->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity' => (int) $item->quantity,
                    'monthly_unit_amount' => (string) $item->monthly_unit_amount,
                    'one_time_unit_amount' => (string) $item->one_time_unit_amount,
                ])->values()->all(),
            ];
            return OpportunityProposal::query()->create([
                'tenant_id' => $tenant->id,
                'opportunity_id' => $opportunityId,
                'version' => $version,
                'snapshot' => $snapshot,
                'public_token' => Str::random(64),
                'expires_at' => now()->addDays((int) $data['validity_days']),
            ]);
        });
        return response()->json(['data' => $this->present($proposal)], 201);
    }

    public function revoke(Request $request, string $tenantId, string $opportunityId, string $proposalId, OpportunityAction $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()->can('updateOpportunities', $tenant), 403);
        $action->find($tenant, $request->user(), $opportunityId);
        $proposal = OpportunityProposal::query()->where('tenant_id', $tenant->id)
            ->where('opportunity_id', $opportunityId)->findOrFail($proposalId);
        $proposal->update(['revoked_at' => $proposal->revoked_at ?? now()]);
        return response()->json(['data' => $this->present($proposal)]);
    }

    public function publicShow(string $token): JsonResponse
    {
        $proposal = OpportunityProposal::query()->where('public_token', $token)
            ->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
        return response()->json(['data' => [
            'version' => $proposal->version,
            'expires_at' => $proposal->expires_at,
            'snapshot' => $proposal->snapshot,
        ]]);
    }

    private function present(OpportunityProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'version' => $proposal->version,
            'created_at' => $proposal->created_at,
            'expires_at' => $proposal->expires_at,
            'revoked_at' => $proposal->revoked_at,
            'public_token' => $proposal->public_token,
            'snapshot' => $proposal->snapshot,
        ];
    }
}
