<?php
namespace App\Actions\Client;
use App\Models\Client;
use App\Models\ClientOperatorHistory;
use App\Models\ClientSeller;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SaveClientAction {
 public function execute(string $tenantId,int $userId,array $data,?string $clientId=null):Client {
  $membership=TenantUser::where('tenant_id',$tenantId)->where('user_id',$userId)->where('status','ACTIVE')->first();
  $canAssign=$membership && (in_array($membership->role->value,['OWNER','MANAGER'],true)||$membership->supervisedTeams()->exists());
  $sellerId=$data['seller_membership_id']??null;$reason=$data['seller_reason']??null;
  if(!$canAssign){
   abort_unless($membership,403,'Usuário sem vínculo ativo com a empresa.');
   $sellerId=$clientId?ClientSeller::where('tenant_id',$tenantId)->where('client_id',$clientId)->whereNull('ended_at')->value('tenant_user_id'):$membership->id;
   $reason=null;
  }
  unset($data['seller_membership_id'],$data['seller_reason']);
  if($sellerId&&!TenantUser::where('tenant_id',$tenantId)->where('status','ACTIVE')->whereKey($sellerId)->exists())throw ValidationException::withMessages(['seller_membership_id'=>'Vendedor não está ativo nesta empresa.']);
  return DB::transaction(function()use($tenantId,$userId,$clientId,$data,$sellerId,$reason){
   $client=$clientId?Client::where('tenant_id',$tenantId)->lockForUpdate()->findOrFail($clientId):new Client(['tenant_id'=>$tenantId]);
   $oldOperator=$client->operator_id;$oldClass=$client->classification_id;$isNew=!$client->exists;
   $client->fill($data);$client->save();
   if($isNew||(string)$oldOperator!==(string)$client->operator_id||(string)$oldClass!==(string)$client->classification_id){
    ClientOperatorHistory::create(['tenant_id'=>$tenantId,'client_id'=>$client->id,'operator_id'=>$client->operator_id,'classification_id'=>$client->classification_id,'changed_at'=>now(),'changed_by'=>$userId]);
   }
   $active=ClientSeller::where('tenant_id',$tenantId)->where('client_id',$client->id)->whereNull('ended_at')->lockForUpdate()->first();
   if(($active?->tenant_user_id)!==$sellerId){
    if($active){$active->ended_at=now();$active->reason=$reason;$active->save();}
    if($sellerId)ClientSeller::create(['tenant_id'=>$tenantId,'client_id'=>$client->id,'tenant_user_id'=>$sellerId,'assigned_by'=>$userId,'started_at'=>now(),'reason'=>$reason]);
   }
   return $client->load(['operator:id,name','classification:id,code,name','contacts','sellers'=>fn($q)=>$q->with('membership')->orderByDesc('started_at'),'operatorHistory'=>fn($q)=>$q->with(['operator:id,name','classification:id,code,name'])->orderByDesc('changed_at')]);
  });
 }
}
