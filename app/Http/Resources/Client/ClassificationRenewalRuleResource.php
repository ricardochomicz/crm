<?php
namespace App\Http\Resources\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClassificationRenewalRuleResource extends JsonResource {
 public function toArray(Request $request):array {return ['id'=>$this->id,'classification_id'=>$this->classification_id,'tenant_id'=>$this->tenant_id,'minimum_months'=>$this->minimum_months,'valid_from'=>$this->valid_from?->format('Y-m-d'),'valid_until'=>$this->valid_until?->format('Y-m-d'),'is_active'=>$this->is_active,'notes'=>$this->notes];}
}
