<?php
namespace App\Http\Resources\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClientResource extends JsonResource {
 public function toArray(Request $request):array {
  return [
   'id'=>$this->id,'tenant_id'=>$this->tenant_id,'person_type'=>$this->person_type,'document'=>$this->document,
   'company_name'=>$this->company_name,'phone'=>$this->phone,'email'=>$this->email,'zip_code'=>$this->zip_code,
   'address'=>$this->address,'address_number'=>$this->address_number,'address_complement'=>$this->address_complement,
   'neighborhood'=>$this->neighborhood,'city'=>$this->city,'state'=>$this->state,
   'operator_id'=>$this->operator_id,'classification_id'=>$this->classification_id,
   'contract_started_on'=>$this->contract_started_on?->format('Y-m-d'),
   'contract_ends_on'=>$this->contract_ends_on?->format('Y-m-d'),'is_active'=>$this->is_active,
   'operator'=>$this->whenLoaded('operator',fn()=> $this->operator?->only(['id','name'])),
   'classification'=>$this->whenLoaded('classification',fn()=> $this->classification?->only(['id','code','name'])),
   'contacts'=>$this->whenLoaded('contacts'),
   'sellers'=>$this->whenLoaded('sellers'),
   'operator_history'=>$this->whenLoaded('operatorHistory'),
   'created_at'=>$this->created_at?->toISOString(),'updated_at'=>$this->updated_at?->toISOString(),
  ];
 }
}
