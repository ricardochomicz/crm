<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\SaveClientAction;
use App\Actions\Client\SaveClientContactAction;
use App\Http\Requests\Client\SaveClientContactRequest;
use App\Http\Resources\Client\ClientContactResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\SaveClientRequest;
use App\Http\Resources\Client\ClientResource;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\TenantUser;
use App\Queries\Client\ClientIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller {
 private function tenant(Request $request,bool $write=false) {
  $tenant=$request->attributes->get('active_tenant');
  abort_unless($request->user()->can($write?'updateClients':'viewClients',$tenant),403);
  return $tenant;
 }
 public function index(IndexClientRequest $request,string $tenantId,ClientIndexQuery $query) {
  $tenant=$request->attributes->get('active_tenant');
  abort_unless($request->user()->can('viewClients',$tenant),403);
  $filters=$request->validated();
  $clients=isset($filters['per_page'])?$query->paginate($tenant->id,$filters):$query->get($tenant->id,$filters);
  return ClientResource::collection($clients)->additional(['meta'=>['can_manage'=>$request->user()->can('updateClients',$tenant),'can_create'=>$request->user()->can('createClients',$tenant),'can_delete'=>$request->user()->can('deleteClients',$tenant)]]);
 }
 public function show(Request $request,string $tenantId,string $clientId):ClientResource {
  $tenant=$this->tenant($request);
  $client=Client::where('tenant_id',$tenant->id)->findOrFail($clientId);
  return new ClientResource($client->load(['operator:id,name','classification:id,code,name','contacts','sellers'=>fn($q)=>$q->with('membership')->orderByDesc('started_at'),'operatorHistory'=>fn($q)=>$q->with(['operator:id,name','classification:id,code,name'])->orderByDesc('changed_at')]));
 }
 public function options(Request $request,string $tenantId): array {
  $tenant=$this->tenant($request);
  $membership=TenantUser::where('tenant_id',$tenant->id)->where('user_id',$request->user()->id)->where('status','ACTIVE')->first();
  $canAssign=$membership && (in_array($membership->role->value,['OWNER','MANAGER'],true) || $membership->supervisedTeams()->exists());
  return ['data'=>['sellers'=>TenantUser::where('tenant_id',$tenant->id)->where('status','ACTIVE')->with('user:id,name,email')->get()->map(fn($m)=>['id'=>$m->id,'name'=>$m->user?->name??$m->user?->email??$m->id])->values(),'current_seller_id'=>$membership?->id,'can_assign_seller'=>(bool)$canAssign]];
 }

 public function destroy(Request $request,string $tenantId,string $clientId): \Illuminate\Http\Response {
  $tenant=$request->attributes->get('active_tenant');
  abort_unless($request->user()->can('deleteClients',$tenant),403);
  Client::where('tenant_id',$tenant->id)->findOrFail($clientId)->delete();
  return response()->noContent();
 }
 public function store(SaveClientRequest $request,string $tenantId,SaveClientAction $action):ClientResource {
  return new ClientResource($action->execute($request->attributes->get('active_tenant')->id,$request->user()->id,$request->validatedClient()));
 }
 public function update(SaveClientRequest $request,string $tenantId,string $clientId,SaveClientAction $action):ClientResource {
  return new ClientResource($action->execute($request->attributes->get('active_tenant')->id,$request->user()->id,$request->validatedClient(),$clientId));
 }
 public function saveContact(SaveClientContactRequest $request,string $tenantId,string $clientId,SaveClientContactAction $action,?string $contactId=null):ClientContactResource {
  $tenant=$request->attributes->get('active_tenant');
  return new ClientContactResource($action->execute($tenant->id,$clientId,$request->validated(),$contactId));
 }
}
