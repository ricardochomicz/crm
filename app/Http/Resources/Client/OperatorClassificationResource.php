<?php
namespace App\Http\Resources\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class OperatorClassificationResource extends JsonResource {
 public function toArray(Request $request):array {return ['id'=>$this->id,'operator_id'=>$this->operator_id,'tenant_id'=>$this->tenant_id,'code'=>$this->code,'name'=>$this->name,'description'=>$this->description,'is_active'=>$this->is_active,'sort_order'=>$this->sort_order,'operator'=>$this->whenLoaded('operator',fn()=>$this->operator?->only(['id','name'])),'rules'=>ClassificationRenewalRuleResource::collection($this->whenLoaded('rules'))];}
}
