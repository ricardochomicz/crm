<?php
namespace App\Actions\Client;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;
class SaveClientContactAction {
 public function __construct(private ScopeVisibleClientsAction $visibility) {}

 public function execute(string $tenantId,string $clientId,array $data,int $userId,?string $contactId=null):ClientContact {
  return DB::transaction(function()use($tenantId,$clientId,$contactId,$data,$userId){
   $client=$this->visibility->execute(Client::query(),$tenantId,$userId)->lockForUpdate()->findOrFail($clientId);
   if($data['is_primary'])ClientContact::where('tenant_id',$tenantId)->where('client_id',$client->id)->update(['is_primary'=>false]);
   $contact=$contactId?ClientContact::where('tenant_id',$tenantId)->where('client_id',$client->id)->findOrFail($contactId):new ClientContact(['tenant_id'=>$tenantId,'client_id'=>$client->id]);
   $contact->fill($data);$contact->save();return $contact;
  });
 }
}
