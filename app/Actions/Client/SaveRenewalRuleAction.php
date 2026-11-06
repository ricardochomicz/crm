<?php
namespace App\Actions\Client;
use App\Models\ClassificationRenewalRule;
use App\Models\OperatorClassification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SaveRenewalRuleAction {
 public function execute(?string $tenantId,int $classificationId,array $data,?string $ruleId=null):ClassificationRenewalRule {
  $classification=OperatorClassification::where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenantId))->findOrFail($classificationId);
  if($tenantId===null && $classification->tenant_id!==null)abort(403);
  if($tenantId!==null && !DB::table('tenant_operators')->where('tenant_id',$tenantId)->where('operator_id',$classification->operator_id)->exists())abort(422);
  return DB::transaction(function()use($tenantId,$classification,$data,$ruleId){
   $scope=ClassificationRenewalRule::where('classification_id',$classification->id)->where('tenant_id',$tenantId);
   $existing=$ruleId?(clone $scope)->lockForUpdate()->findOrFail($ruleId):null;
   if($data['is_active']&&(clone $scope)->when($existing,fn($q)=>$q->where('id','!=',$existing->id))->where('is_active',true)->where('valid_from','<=',$data['valid_until']??'9999-12-31')->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>=',$data['valid_from']))->exists())throw ValidationException::withMessages(['valid_from'=>'Já existe uma regra ativa com vigência sobreposta. Encerre a regra anterior primeiro.']);
   if($existing){$existing->update($data);return $existing;}
   return ClassificationRenewalRule::create(['classification_id'=>$classification->id,'tenant_id'=>$tenantId,...$data]);
  });
 }
}
