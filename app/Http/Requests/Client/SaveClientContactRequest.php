<?php
namespace App\Http\Requests\Client;
use Illuminate\Foundation\Http\FormRequest;
class SaveClientContactRequest extends FormRequest {
 public function authorize():bool {$tenant=$this->attributes->get('active_tenant');return $tenant && $this->user()?->can('updateClients',$tenant);}
 protected function prepareForValidation():void {if($this->filled('cpf'))$this->merge(['cpf'=>preg_replace('/\D/','',(string)$this->input('cpf'))]);if($this->filled('phone'))$this->merge(['phone'=>preg_replace('/\D/','',(string)$this->input('phone'))]);}
 public function rules():array {return ['name'=>['required','string','max:200'],'cpf'=>['nullable','digits:11'], 'birth_date'=>['nullable','date','before_or_equal:today'], 'position'=>['nullable','string','max:100'],'phone'=>['nullable','string','max:20'],'email'=>['nullable','email','max:255'],'is_primary'=>['required','boolean'],'is_active'=>['required','boolean'],'portal_access_enabled'=>['sometimes','boolean'],'notes'=>['nullable','string','max:5000']];}
}
