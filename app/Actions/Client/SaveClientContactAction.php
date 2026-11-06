<?php
namespace App\Actions\Client;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;
class SaveClientContactAction {
 public function execute(string $tenantId,string $clientId,array $data,?string $contactId=null):ClientContact {
  $client=Client::where('tenant_id',$tenantId)->findOrFail($clientId);
  return DB::transaction(function()use($tenantId,$client,$contactId,$data){
   if($data['is_primary'])ClientContact::where('tenant_id',$tenantId)->where('client_id',$client->id)->update(['is_primary'=>false]);
   $contact=$contactId?ClientContact::where('tenant_id',$tenantId)->where('client_id',$client->id)->findOrFail($contactId):new ClientContact(['tenant_id'=>$tenantId,'client_id'=>$client->id]);
   $contact->fill($data);$contact->save();return $contact;
  });
 }
}
