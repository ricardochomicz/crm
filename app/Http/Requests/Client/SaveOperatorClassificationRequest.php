<?php
namespace App\Http\Requests\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class SaveOperatorClassificationRequest extends FormRequest {
 public function authorize():bool {
  if(str_contains((string)$this->path(),'/admin/'))return (bool)$this->user()?->is_platform_admin;
  $tenant=$this->attributes->get('active_tenant');
  return $tenant && $tenant->users()->where('users.id',$this->user()->id)->wherePivot('role','OWNER')->exists();
 }
 public function rules():array {
  $global=str_contains((string)$this->path(),'/admin/');
  $tenant=$this->attributes->get('active_tenant');
  return ['operator_id'=>['required','integer',Rule::exists($global?'operators':'tenant_operators',$global?'id':'operator_id')->when(!$global,fn($r)=>$r->where('tenant_id',$tenant?->id))],
   'code'=>['required','string','max:30'],'name'=>['required','string','max:100'],'description'=>['nullable','string','max:255'],'is_active'=>['required','boolean'],'sort_order'=>['nullable','integer','min:0']];
 }
}
