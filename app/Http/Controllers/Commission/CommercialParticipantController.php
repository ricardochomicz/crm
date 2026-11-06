<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterCommercialParticipantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexCommercialParticipantRequest;
use App\Http\Requests\Commission\SaveCommercialParticipantCommissionRequest;
use App\Http\Requests\Commission\SaveCommercialParticipantRequest;
use App\Http\Resources\Commission\CommercialParticipantCommissionResource;
use App\Http\Resources\Commission\CommercialParticipantResource;
use App\Http\Resources\Commission\TeamCommissionRuleResource;
use App\Models\CommercialParticipant;
use App\Models\CommercialParticipantCommission;
use App\Models\TeamCommissionRule;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialParticipantController extends Controller
{
    private function tenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can($request->isMethod('GET') ? 'viewCommissions' : 'manageCommissions', $tenant), 403);

        return $tenant;
    }

    public function index(IndexCommercialParticipantRequest $request, string $tenantId, FilterCommercialParticipantAction $filter): array
    {
        $tenant = $this->tenant($request);
        $page = $filter->execute($tenant->id, $request->validated());

        return ['data' => CommercialParticipantResource::collection($page->getCollection()), 'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    public function store(SaveCommercialParticipantRequest $request, string $tenantId): array
    {
        return $this->save($request);
    }

    public function update(SaveCommercialParticipantRequest $request, string $tenantId, string $participantId): array
    {
        return $this->save($request, $participantId);
    }

    private function save(SaveCommercialParticipantRequest $request, ?string $participantId = null): array
    {
        $tenant = $this->tenant($request);
        $data = $request->validated();
        if (! empty($data['document']) && strlen($data['document']) !== ($data['person_type'] === 'PF' ? 11 : 14)) {
            throw ValidationException::withMessages(['document' => 'Documento incompatível com o tipo de pessoa.']);
        }
        $duplicate = CommercialParticipant::where('tenant_id', $tenant->id)->where('document', $data['document'] ?? null);
        if (! empty($data['document']) && $duplicate->when($participantId, fn ($q) => $q->where('id', '!=', $participantId))->exists()) {
            throw ValidationException::withMessages(['document' => 'Documento já cadastrado nesta empresa.']);
        }
        $record = $participantId ? CommercialParticipant::where('tenant_id', $tenant->id)->findOrFail($participantId) : new CommercialParticipant(['tenant_id' => $tenant->id]);
        $record->fill($data);
        $record->save();

        return ['data' => new CommercialParticipantResource($record)];
    }

    public function commissions(Request $request, string $tenantId, string $participantId): array
    {
        $tenant = $this->tenant($request);
        $participant = CommercialParticipant::where('tenant_id', $tenant->id)->findOrFail($participantId);
        $defaults = TeamCommissionRule::where('tenant_id', $tenant->id)->where('commercial_role', $participant->role)->where('is_active', true)->with('operator:id,name')->get();
        $overrides = $participant->commissions()->with('operator:id,name')->orderBy('order_type')->get();

        return ['data' => ['defaults' => TeamCommissionRuleResource::collection($defaults), 'overrides' => CommercialParticipantCommissionResource::collection($overrides)]];
    }

    public function saveCommission(SaveCommercialParticipantCommissionRequest $request, string $tenantId, string $participantId, ?string $overrideId = null): array
    {
        $tenant = $this->tenant($request);
        $participant = CommercialParticipant::where('tenant_id', $tenant->id)->findOrFail($participantId);
        $data = $request->validated();

        return DB::transaction(function () use ($tenant, $participant, $data, $overrideId) {
            $query = CommercialParticipantCommission::where('tenant_id', $tenant->id)->where('commercial_participant_id', $participant->id);
            $record = $overrideId ? (clone $query)->lockForUpdate()->findOrFail($overrideId) : null;
            if ($data['is_active']) {
                $overlap = (clone $query)->where('operator_id', $data['operator_id'])->where('order_type', $data['order_type'])
                    ->where('is_active', true)->where('starts_on', '<=', $data['ends_on'] ?? '9999-12-31')
                    ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']));
                if ($record) {
                    $overlap->where('id', '!=', $record->id);
                }
                if ($overlap->exists()) {
                    throw ValidationException::withMessages(['starts_on' => 'Já existe uma exceção ativa com vigência sobreposta.']);
                }
            }
            if ($record) {
                $record->update($data);
            } else {
                $record = CommercialParticipantCommission::create(['tenant_id' => $tenant->id, 'commercial_participant_id' => $participant->id, ...$data]);
            }

            return ['data' => new CommercialParticipantCommissionResource($record->fresh('operator:id,name'))];
        });
    }

    public function deleteCommission(Request $request, string $tenantId, string $participantId, string $overrideId): array
    {
        $tenant = $this->tenant($request);
        $participant = CommercialParticipant::where('tenant_id', $tenant->id)->findOrFail($participantId);
        $participant->commissions()->where('tenant_id', $tenant->id)->findOrFail($overrideId)->delete();

        return ['data' => ['deleted' => true]];
    }
}
