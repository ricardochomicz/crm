<?php
namespace App\Http\Resources\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClientContactResource extends JsonResource {
 public function toArray(Request $request):array {return ['id'=>$this->id,'tenant_id'=>$this->tenant_id,'client_id'=>$this->client_id,'name'=>$this->name,'position'=>$this->position,'phone'=>$this->phone,'email'=>$this->email,'is_primary'=>$this->is_primary,'is_active'=>$this->is_active,'portal_access_enabled'=>$this->portal_access_enabled,'notes'=>$this->notes,'created_at'=>$this->created_at?->toISOString(),'updated_at'=>$this->updated_at?->toISOString()];}
}
