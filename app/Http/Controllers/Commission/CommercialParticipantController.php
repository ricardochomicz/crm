<?php

namespace App\Http\Controllers\Commission;

use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\CommercialParticipant;
use App\Models\CommercialParticipantCommission;
use App\Models\TeamCommissionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommercialParticipantController extends Controller
{
 private function tenant(Request $request)
 {
  $tenant = $request->attributes->get('active_tenant');
  abort_unless($request->user()->can($request->isMethod('GET') ? 'viewCommissions' : 'manageCommissions', $tenant), 403);
  return $tenant;
 }

 public function index(Request $request, string $tenantId): array
 {
  $tenant = $this->tenant($request);
  return ['data' => CommercialParticipant::where('tenant_id', $tenant->id)->orderBy('name')->get(),
   'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)]];
 }

 public function store(Request $request, string $tenantId): array { return $this->save($request); }
 public function update(Request $request, string $tenantId, string $participantId): array { return $this->save($request, $participantId); }

 private function save(Request $request, ?string $participantId = null): array
 {
  $tenant = $this->tenant($request);
  $data = $request->validate([
   'role' => ['required', Rule::in(['PARTNER','REFERRER'])],
   'person_type' => ['required', Rule::in(['PF','PJ'])],
   'name' => ['required','string','max:200'],
   'document' => ['nullable','string','regex:/^\\d{11}(\\d{3})?$/'],
   'email' => ['nullable','email','max:255'],
   'phone' => ['nullable','string','max:30'],
   'notes' => ['nullable','string','max:5000'],
   'is_active' => ['required','boolean'],
  ]);
  if (!empty($data['document']) && strlen($data['document']) !== ($data['person_type'] === 'PF' ? 11 : 14)) {
   throw ValidationException::withMessages(['document'=>'Documento incompatível com o tipo de pessoa.']);
  }
  $duplicate = CommercialParticipant::where('tenant_id',$tenant->id)->where('document',$data['document'] ?? null);
  if (!empty($data['document']) && $duplicate->when($participantId, fn($q)=>$q->where('id','!=',$participantId))->exists()) {
   throw ValidationException::withMessages(['document'=>'Documento já cadastrado nesta empresa.']);
  }
  $record = $participantId ? CommercialParticipant::where('tenant_id',$tenant->id)->findOrFail($participantId) : new CommercialParticipant(['tenant_id'=>$tenant->id]);
  $record->fill($data); $record->save();
  return ['data'=>$record];
 }

 public function commissions(Request $request, string $tenantId, string $participantId): array
 {
  $tenant = $this->tenant($request);
  $participant = CommercialParticipant::where('tenant_id',$tenant->id)->findOrFail($participantId);
  $defaults = TeamCommissionRule::where('tenant_id',$tenant->id)->where('commercial_role',$participant->role)->where('is_active',true)->with('operator:id,name')->get();
  $overrides = $participant->commissions()->with('operator:id,name')->orderBy('order_type')->get();
  return ['data'=>['defaults'=>$defaults,'overrides'=>$overrides]];
 }

 public function saveCommission(Request $request, string $tenantId, string $participantId, ?string $overrideId = null): array
 {
  $tenant = $this->tenant($request);
  $participant = CommercialParticipant::where('tenant_id',$tenant->id)->findOrFail($participantId);
  $data = $request->validate([
   'operator_id'=>['required','integer',Rule::exists('tenant_operators','operator_id')->where('tenant_id',$tenant->id)],
   'order_type'=>['required',Rule::enum(OrderType::class)],
   'percentage'=>['required','numeric','min:0','max:9999.99','decimal:0,2'],
   'starts_on'=>['required','date_format:Y-m-d'],
   'ends_on'=>['nullable','date_format:Y-m-d','after_or_equal:starts_on'],
   'is_active'=>['required','boolean'],
  ]);
  return DB::transaction(function () use ($tenant,$participant,$data,$overrideId) {
   $query = CommercialParticipantCommission::where('tenant_id',$tenant->id)->where('commercial_participant_id',$participant->id);
   $record = $overrideId ? (clone $query)->lockForUpdate()->findOrFail($overrideId) : null;
   if ($data['is_active']) {
    $overlap = (clone $query)->where('operator_id',$data['operator_id'])->where('order_type',$data['order_type'])
     ->where('is_active',true)->where('starts_on','<=',$data['ends_on'] ?? '9999-12-31')
     ->where(fn($q)=>$q->whereNull('ends_on')->orWhere('ends_on','>=',$data['starts_on']));
    if ($record) $overlap->where('id','!=',$record->id);
    if ($overlap->exists()) throw ValidationException::withMessages(['starts_on'=>'Já existe uma exceção ativa com vigência sobreposta.']);
   }
   if ($record) $record->update($data);
   else $record = CommercialParticipantCommission::create(['tenant_id'=>$tenant->id,'commercial_participant_id'=>$participant->id,...$data]);
   return ['data'=>$record->fresh('operator:id,name')];
  });
 }

 public function deleteCommission(Request $request, string $tenantId, string $participantId, string $overrideId): array
 {
  $tenant = $this->tenant($request);
  $participant = CommercialParticipant::where('tenant_id',$tenant->id)->findOrFail($participantId);
  $participant->commissions()->where('tenant_id',$tenant->id)->findOrFail($overrideId)->delete();
  return ['data'=>['deleted'=>true]];
 }
}
