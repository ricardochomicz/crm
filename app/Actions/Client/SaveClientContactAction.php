<?php
namespace App\Actions\Client;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SaveClientContactAction {
 public function __construct(private ScopeVisibleClientsAction $visibility) {}

 public function execute(string $tenantId,string $clientId,array $data,int $userId,?string $contactId=null):ClientContact {
  return DB::transaction(function()use($tenantId,$clientId,$contactId,$data,$userId){
   $client=$this->visibility->execute(Client::query(),$tenantId,$userId)->lockForUpdate()->findOrFail($clientId);
   if($client->contact_pending && (!$data['is_primary'] || !$data['is_active'] || !trim((string)($data['position'] ?? '')) || (!trim((string)($data['phone'] ?? '')) && !trim((string)($data['email'] ?? ''))))) {
    throw ValidationException::withMessages(['contact' => 'O primeiro contato deve ser principal e ativo, com cargo e telefone ou e-mail.']);
   }
   if($data['is_primary'])ClientContact::where('tenant_id',$tenantId)->where('client_id',$client->id)->update(['is_primary'=>false]);
   $contact=$contactId?ClientContact::where('tenant_id',$tenantId)->where('client_id',$client->id)->findOrFail($contactId):new ClientContact(['tenant_id'=>$tenantId,'client_id'=>$client->id]);
   if (array_key_exists('portal_access_enabled', $data) && $data['portal_access_enabled'] && (!$data['is_active'] || empty($data['email']))) { throw ValidationException::withMessages(['portal_access_enabled' => 'Informe um e-mail e mantenha o contato ativo para habilitar o portal.']); }
   // Alterar o endereço exige nova autorização explícita em uma edição posterior.
   if ($contact->exists && array_key_exists('email', $data) && strcasecmp((string) $contact->email, (string) $data['email']) !== 0) {
    $data['portal_access_enabled'] = false;
   }
   $contact->fill($data);$contact->save();
   if($client->contact_pending && $contact->is_active && $contact->is_primary && $contact->name && $contact->position && ($contact->phone || $contact->email)){
    $client->contact_pending=false;$client->save();
   }
   return $contact;
  });
 }
}
