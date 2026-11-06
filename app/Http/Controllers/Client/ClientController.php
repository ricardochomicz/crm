<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientOperatorHistory;
use App\Models\ClientSeller;
use App\Models\OperatorClassification;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller {
 private function tenant(Request $request,bool $write=false) {
  $tenant=$request->attributes->get('active_tenant');
  abort_unless($request->user()->can($write?'manageCommissions':'viewCommissions',$tenant),403);
  return $tenant;
 }
 private function detail(Client $client): Client {
  return $client->load(['operator:id,name','classification:id,code,name','contacts','sellers'=>fn($q)=>$q->with('membership')->orderByDesc('started_at'),'operatorHistory'=>fn($q)=>$q->with(['operator:id,name','classification:id,code,name'])->orderByDesc('changed_at')]);
 }
 public function index(Request $request,string $tenantId): array {
  $tenant=$this->tenant($request);
  return ['data'=>Client::where('tenant_id',$tenant->id)->with(['operator:id,name','classification:id,code,name','sellers'=>fn($q)=>$q->whereNull('ended_at')->with('membership')])->orderBy('company_name')->limit(500)->get(),
   'meta'=>['can_manage'=>$request->user()->can('manageCommissions',$tenant)]];
 }
 public function show(Request $request,string $tenantId,string $clientId): array {
  $tenant=$this->tenant($request);
  return ['data'=>$this->detail(Client::where('tenant_id',$tenant->id)->findOrFail($clientId))];
 }
 public function options(Request $request,string $tenantId): array {
  $tenant=$this->tenant($request);
  $membership=TenantUser::where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)->where('status','ACTIVE')->first();
  $canAssign=$membership && (in_array($membership->role->value,['OWNER','MANAGER'],true) || $membership->supervisedTeams()->exists());
  return ['data'=>['sellers'=>TenantUser::where('tenant_id',$tenant->id)->where('status','ACTIVE')->with('user:id,name,email')->get()->map(fn($m)=>['id'=>$m->id,'name'=>$m->user?->name??$m->user?->email??$m->id])->values(),'current_seller_id'=>$membership?->id,'can_assign_seller'=>(bool)$canAssign]];
 }
 public function store(Request $request,string $tenantId): array {return $this->save($request);}
 public function update(Request $request,string $tenantId,string $clientId): array {return $this->save($request,$clientId);}
 private function save(Request $request,?string $clientId=null): array {
  $tenant=$this->tenant($request,true);
  $data=$request->validate([
   'person_type'=>['required',Rule::in(['PF','PJ'])],
   'document'=>['required','string','regex:/^(\\d{11}|\\d{14})$/',Rule::unique('clients','document')->where('tenant_id',$tenant->id)->ignore($clientId)],
   'company_name'=>['required','string','max:255'],
   'phone'=>['nullable','string','max:20'],'email'=>['nullable','email','max:255'],
   'zip_code'=>['nullable','string','regex:/^\\d{8}$/'],
   'address'=>['nullable','string','max:255'],'address_number'=>['nullable','string','max:20'],
   'address_complement'=>['nullable','string','max:255'],'neighborhood'=>['nullable','string','max:150'],
   'city'=>['nullable','string','max:150'],'state'=>['nullable','string','size:2'],
   'operator_id'=>['nullable','integer',Rule::exists('operators','id')],
   'classification_id'=>['nullable','integer'],
   'contract_started_on'=>['nullable','date_format:Y-m-d'],
   'contract_ends_on'=>['nullable','date_format:Y-m-d','after_or_equal:contract_started_on'],
   'is_active'=>['required','boolean'],
   'seller_membership_id'=>['nullable','uuid'],
   'seller_reason'=>['nullable','string','max:255'],
  ]);
  if(strlen($data['document'])!==($data['person_type']==='PF'?11:14))throw ValidationException::withMessages(['document'=>'CPF/CNPJ incompatível com o tipo de pessoa.']);
  if(!empty($data['classification_id'])) {
   $valid=OperatorClassification::where('id',$data['classification_id'])->where('operator_id',$data['operator_id']??null)
    ->where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenant->id))->exists();
   if(!$valid)throw ValidationException::withMessages(['classification_id'=>'Classificação não pertence à operadora ou à empresa.']);
  }
  $membership=TenantUser::where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)->where('status','ACTIVE')->first();
  $canAssign=$membership && (in_array($membership->role->value,['OWNER','MANAGER'],true) || $membership->supervisedTeams()->exists());
  $sellerId=$data['seller_membership_id']??null; $reason=$data['seller_reason']??null;
  if(!$canAssign){
   abort_unless($membership,403,'Usuário sem vínculo ativo com a empresa.');
   if($clientId){
    $currentSeller=ClientSeller::where('tenant_id',$tenant->id)->where('client_id',$clientId)->whereNull('ended_at')->value('tenant_user_id');
    $sellerId=$currentSeller;
   }else{$sellerId=$membership->id;}
   $reason=null;
  }
  unset($data['seller_membership_id'],$data['seller_reason']);
  if($sellerId&&!TenantUser::where('tenant_id',$tenant->id)->where('status','ACTIVE')->whereKey($sellerId)->exists())
   throw ValidationException::withMessages(['seller_membership_id'=>'Vendedor não está ativo nesta empresa.']);
  return DB::transaction(function()use($tenant,$clientId,$data,$sellerId,$reason,$request){
   $client=$clientId?Client::where('tenant_id',$tenant->id)->lockForUpdate()->findOrFail($clientId):new Client(['tenant_id'=>$tenant->id]);
   $oldOperator=$client->operator_id; $oldClass=$client->classification_id;
   $isNew=!$client->exists;
   $client->fill($data);$client->save();
   if($isNew||(string)$oldOperator!==(string)$client->operator_id||(string)$oldClass!==(string)$client->classification_id){
    ClientOperatorHistory::create(['tenant_id'=>$tenant->id,'client_id'=>$client->id,'operator_id'=>$client->operator_id,'classification_id'=>$client->classification_id,'changed_at'=>now(),'changed_by'=>$request->user()->id]);
   }
   $active=ClientSeller::where('tenant_id',$tenant->id)->where('client_id',$client->id)->whereNull('ended_at')->lockForUpdate()->first();
   if(($active?->tenant_user_id)!==$sellerId){
    if($active){$active->ended_at=now();$active->reason=$reason;$active->save();}
    if($sellerId)ClientSeller::create(['tenant_id'=>$tenant->id,'client_id'=>$client->id,'tenant_user_id'=>$sellerId,'assigned_by'=>$request->user()->id,'started_at'=>now(),'reason'=>$reason]);
   }
   return ['data'=>$this->detail($client)];
  });
 }
 public function saveContact(Request $request,string $tenantId,string $clientId,?string $contactId=null): array {
  $tenant=$this->tenant($request,true);$client=Client::where('tenant_id',$tenant->id)->findOrFail($clientId);
  $data=$request->validate(['name'=>['required','string','max:200'],'position'=>['nullable','string','max:100'],'phone'=>['nullable','string','max:20'],'email'=>['nullable','email','max:255'],'is_primary'=>['required','boolean'],'is_active'=>['required','boolean'],'notes'=>['nullable','string','max:5000']]);
  return DB::transaction(function()use($tenant,$client,$contactId,$data){
   if($data['is_primary'])ClientContact::where('tenant_id',$tenant->id)->where('client_id',$client->id)->update(['is_primary'=>false]);
   $contact=$contactId?ClientContact::where('tenant_id',$tenant->id)->where('client_id',$client->id)->findOrFail($contactId):new ClientContact(['tenant_id'=>$tenant->id,'client_id'=>$client->id]);
   $contact->fill($data);$contact->save();return ['data'=>$contact];
  });
 }
}
