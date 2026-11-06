<?php
namespace App\Http\Requests\Client;
use App\Models\OperatorClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class SaveClientRequest extends FormRequest {
 public function authorize():bool {$tenant=$this->attributes->get('active_tenant');return $tenant && $this->user()?->can('manageCommissions',$tenant);}
 protected function prepareForValidation():void {
  $clean=[];
  foreach(['document','phone','zip_code'] as $field)if($this->has($field)&&$this->input($field)!==null)$clean[$field]=preg_replace('/\D/','',(string)$this->input($field));
  $this->merge($clean);
 }
 public function rules():array {
  $tenant=$this->attributes->get('active_tenant');
  $clientId=$this->route('clientId');
  return [
   'person_type'=>['required',Rule::in(['PF','PJ'])],
   'document'=>['required','string','regex:/^(\d{11}|\d{14})$/',Rule::unique('clients','document')->where('tenant_id',$tenant->id)->ignore($clientId)],
   'company_name'=>['required','string','max:255'],
   'phone'=>['nullable','string','max:20'],'email'=>['nullable','email','max:255'],
   'zip_code'=>['nullable','string','regex:/^\d{8}$/'],
   'address'=>['nullable','string','max:255'],'address_number'=>['nullable','string','max:20'],
   'address_complement'=>['nullable','string','max:255'],'neighborhood'=>['nullable','string','max:150'],
   'city'=>['nullable','string','max:150'],'state'=>['nullable','string','size:2'],
   'operator_id'=>['nullable','integer',Rule::exists('operators','id')],
   'classification_id'=>['nullable','integer'],
   'contract_started_on'=>['nullable','date_format:Y-m-d'],
   'contract_ends_on'=>['nullable','date_format:Y-m-d','after_or_equal:contract_started_on'],
   'is_active'=>['required','boolean'],'seller_membership_id'=>['nullable','uuid'],'seller_reason'=>['nullable','string','max:255'],
  ];
 }
 public function messages():array {return ['document.regex'=>'Informe um CPF ou CNPJ com a quantidade correta de dígitos.','document.unique'=>'Este CPF ou CNPJ já está cadastrado.','zip_code.regex'=>'O CEP deve ter 8 dígitos.'];}
 public function validatedClient():array {
  $data=$this->validated();
  if(strlen($data['document'])!==($data['person_type']==='PF'?11:14))throw ValidationException::withMessages(['document'=>'CPF/CNPJ incompatível com o tipo de pessoa.']);
  if(!empty($data['classification_id'])){
   $tenant=$this->attributes->get('active_tenant');
   $valid=OperatorClassification::whereKey($data['classification_id'])->where('operator_id',$data['operator_id']??null)->where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$tenant->id))->exists();
   if(!$valid)throw ValidationException::withMessages(['classification_id'=>'Classificação não pertence à operadora ou à empresa.']);
  }
  return $data;
 }
}
