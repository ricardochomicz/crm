<?php
namespace App\Http\Controllers\Client;
use App\Actions\Client\SaveOperatorClassificationAction;
use App\Actions\Client\SaveRenewalRuleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SaveOperatorClassificationRequest;
use App\Http\Requests\Client\SaveRenewalRuleRequest;
use App\Http\Resources\Client\OperatorClassificationResource;
use App\Http\Resources\Client\ClassificationRenewalRuleResource;
use App\Actions\Client\FilterOperatorClassificationsAction;
use Illuminate\Http\Request;
use App\Http\Requests\Client\IndexOperatorClassificationRequest;
class OperatorClassificationController extends Controller {
 private function context(Request $request,bool $write=false,bool $global=false) {
  if($global){abort_unless((bool)$request->user()->is_platform_admin,403);return null;}
  $tenant=$request->attributes->get('active_tenant');abort_unless($tenant,403);
  if($write)abort_unless($tenant->users()->where('users.id',$request->user()->id)->wherePivot('role','OWNER')->exists(),403);
  else abort_unless($request->user()->can('viewCommissions',$tenant),403);
  return $tenant;
 }
 public function index(IndexOperatorClassificationRequest $request,string $tenantId,FilterOperatorClassificationsAction $query) {
  $tenant=$this->context($request);
  return OperatorClassificationResource::collection($query->execute($tenant->id,$request->validated()))->additional(['meta'=>['can_manage'=>$tenant->users()->where('users.id',$request->user()->id)->wherePivot('role','OWNER')->exists()]]);
 }
 public function globalIndex(IndexOperatorClassificationRequest $request,FilterOperatorClassificationsAction $query) {
  $this->context($request,false,true);
  return OperatorClassificationResource::collection($query->execute(null,$request->validated()));
 }
 public function store(SaveOperatorClassificationRequest $request,string $tenantId,SaveOperatorClassificationAction $action):OperatorClassificationResource {
  $tenant=$this->context($request,true);return new OperatorClassificationResource($action->execute($tenant->id,$request->validated()));
 }
 public function update(SaveOperatorClassificationRequest $request,string $tenantId,int $classificationId,SaveOperatorClassificationAction $action):OperatorClassificationResource {
  $tenant=$this->context($request,true);return new OperatorClassificationResource($action->execute($tenant->id,$request->validated(),$classificationId));
 }
 public function globalStore(SaveOperatorClassificationRequest $request,SaveOperatorClassificationAction $action):OperatorClassificationResource {
  $this->context($request,true,true);return new OperatorClassificationResource($action->execute(null,$request->validated()));
 }
 public function globalUpdate(SaveOperatorClassificationRequest $request,int $classificationId,SaveOperatorClassificationAction $action):OperatorClassificationResource {
  $this->context($request,true,true);return new OperatorClassificationResource($action->execute(null,$request->validated(),$classificationId));
 }
 public function saveRule(SaveRenewalRuleRequest $request,string $tenantId,int $classificationId,SaveRenewalRuleAction $action):ClassificationRenewalRuleResource {
  $tenant=$this->context($request,true);return new ClassificationRenewalRuleResource($action->execute($tenant->id,$classificationId,$request->validated()));
 }
 public function globalSaveRule(SaveRenewalRuleRequest $request,int $classificationId,SaveRenewalRuleAction $action):ClassificationRenewalRuleResource {
  $this->context($request,true,true);return new ClassificationRenewalRuleResource($action->execute(null,$classificationId,$request->validated()));
 }
 public function updateRule(SaveRenewalRuleRequest $request,string $tenantId,int $classificationId,string $ruleId,SaveRenewalRuleAction $action):ClassificationRenewalRuleResource {
  $tenant=$this->context($request,true);return new ClassificationRenewalRuleResource($action->execute($tenant->id,$classificationId,$request->validated(),$ruleId));
 }
 public function globalUpdateRule(SaveRenewalRuleRequest $request,int $classificationId,string $ruleId,SaveRenewalRuleAction $action):ClassificationRenewalRuleResource {
  $this->context($request,true,true);return new ClassificationRenewalRuleResource($action->execute(null,$classificationId,$request->validated(),$ruleId));
 }
}
