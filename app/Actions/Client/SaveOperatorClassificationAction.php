<?php
namespace App\Actions\Client;
use App\Models\OperatorClassification;
use Illuminate\Validation\ValidationException;
class SaveOperatorClassificationAction {
 public function execute(?string $tenantId,array $data,?int $id=null):OperatorClassification {
  $record=$id?OperatorClassification::where('tenant_id',$tenantId)->findOrFail($id):null;
  $duplicate=OperatorClassification::where('operator_id',$data['operator_id'])->where('code',$data['code'])->where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenantId));
  if($record)$duplicate->where('id','!=',$record->id);
  if($duplicate->exists())throw ValidationException::withMessages(['code'=>'Já existe uma classificação com este código disponível neste escopo.']);
  if($record)$record->update($data);
  else $record=OperatorClassification::create([...$data,'tenant_id'=>$tenantId]);
  return $record->fresh(['operator:id,name','rules']);
 }
}
