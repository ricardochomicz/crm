<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClassificationRenewalRule;
use App\Models\OperatorClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperatorClassificationController extends Controller {
 private function context(Request $request,bool $write=false,bool $global=false): array {
  if($global){
   abort_unless((bool)$request->user()->is_platform_admin,403);
   return [null,true];
  }
  $tenant=$request->attributes->get('active_tenant');
  abort_unless($tenant,403);
  if($write)abort_unless($tenant->users()->where('users.id',$request->user()->id)->wherePivot('role','OWNER')->exists(),403);
  else abort_unless($request->user()->can('viewCommissions',$tenant),403);
  return [$tenant,$write];
 }
 public function index(Request $request,string $tenantId): array {
  [$tenant]=$this->context($request);
  $records=OperatorClassification::where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenant->id))
   ->with(['operator:id,name','rules'=>fn($q)=>$q->where(fn($x)=>$x->whereNull('tenant_id')->orWhere('tenant_id',$tenant->id))->orderByDesc('valid_from')])->orderBy('operator_id')->orderBy('sort_order')->orderBy('code')->get();
  return ['data'=>$records,'meta'=>['can_manage'=>$tenant->users()->where('users.id',$request->user()->id)->wherePivot('role','OWNER')->exists()]];
 }
 public function globalIndex(Request $request): array {
  $this->context($request,false,true);
  return ['data'=>OperatorClassification::with(['operator:id,name','rules'=>fn($q)=>$q->whereNull('tenant_id')->orderByDesc('valid_from')])->whereNull('tenant_id')->orderBy('operator_id')->orderBy('code')->get()];
 }
 public function store(Request $request,string $tenantId): array {return $this->save($request,false);}
 public function update(Request $request,string $tenantId,int $classificationId): array {return $this->save($request,false,$classificationId);}
 public function globalStore(Request $request): array {return $this->save($request,true);}
 public function globalUpdate(Request $request,int $classificationId): array {return $this->save($request,true,$classificationId);}
 private function save(Request $request,bool $global,?int $id=null): array {
  [$tenant]=$this->context($request,true,$global);
  $data=$request->validate([
   'operator_id'=>['required','integer',Rule::exists($global?'operators':'tenant_operators',$global?'id':'operator_id')->when(!$global,fn($r)=>$r->where('tenant_id',$tenant?->id))],
   'code'=>['required','string','max:30'],'name'=>['required','string','max:100'],
   'description'=>['nullable','string','max:255'],'is_active'=>['required','boolean'],'sort_order'=>['nullable','integer','min:0'],
  ]);
  $query=OperatorClassification::where('tenant_id',$tenant?->id);
  $record=$id?(clone $query)->findOrFail($id):null;
  $duplicate=OperatorClassification::where('operator_id',$data['operator_id'])->where('code',$data['code'])
   ->where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenant?->id));
  if($record)$duplicate->where('id','!=',$record->id);
  if($duplicate->exists())throw ValidationException::withMessages(['code'=>'Já existe uma classificação com este código disponível neste escopo.']);
  if(!$global&&$record&&$record->tenant_id!==$tenant->id)abort(403);
  if($record)$record->update($data);
  else $record=OperatorClassification::create([...$data,'tenant_id'=>$tenant?->id]);
  return ['data'=>$record->fresh(['operator:id,name','rules'])];
 }
 public function saveRule(Request $request,string $tenantId,int $classificationId): array {return $this->rule($request,false,$classificationId);}
 public function globalSaveRule(Request $request,int $classificationId): array {return $this->rule($request,true,$classificationId);}
 public function updateRule(Request $request,string $tenantId,int $classificationId,string $ruleId): array {return $this->rule($request,false,$classificationId,$ruleId);}
 public function globalUpdateRule(Request $request,int $classificationId,string $ruleId): array {return $this->rule($request,true,$classificationId,$ruleId);}
 private function rule(Request $request,bool $global,int $classificationId,?string $ruleId=null): array {
  [$tenant]=$this->context($request,true,$global);
  $classification=OperatorClassification::where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenant?->id))->findOrFail($classificationId);
  if($global&&$classification->tenant_id!==null)abort(403);
  if(!$global&&!DB::table('tenant_operators')->where('tenant_id',$tenant->id)->where('operator_id',$classification->operator_id)->exists())abort(422);
  $data=$request->validate([
   'minimum_months'=>['required','integer','min:0','max:1200'],'valid_from'=>['required','date_format:Y-m-d'],
   'valid_until'=>['nullable','date_format:Y-m-d','after_or_equal:valid_from'],'is_active'=>['required','boolean'],'notes'=>['nullable','string','max:500'],
  ]);
  return DB::transaction(function()use($tenant,$classification,$data,$ruleId){
   $scope=ClassificationRenewalRule::where('classification_id',$classification->id)->where('tenant_id',$tenant?->id);
   $existing=$ruleId?(clone $scope)->lockForUpdate()->findOrFail($ruleId):null;
   if($data['is_active']&&(clone $scope)->when($existing,fn($q)=>$q->where('id','!=',$existing->id))->where('is_active',true)->where('valid_from','<=',$data['valid_until']??'9999-12-31')
     ->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>=',$data['valid_from']))->exists())
    throw ValidationException::withMessages(['valid_from'=>'Já existe uma regra ativa com vigência sobreposta. Encerre a regra anterior primeiro.']);
   if($existing){$existing->update($data);return ['data'=>$existing];}
   return ['data'=>ClassificationRenewalRule::create(['classification_id'=>$classification->id,'tenant_id'=>$tenant?->id,...$data])];
  });
 }
}
