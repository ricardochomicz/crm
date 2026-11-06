<?php
namespace App\Http\Requests\Client;
use Illuminate\Foundation\Http\FormRequest;
class IndexClientRequest extends FormRequest {
 public function authorize():bool { $tenant=$this->attributes->get('active_tenant');return $tenant && $this->user()?->can('viewClients',$tenant); }
 public function rules():array {return ['is_active'=>['sometimes','boolean'],'search'=>['nullable','string','max:255'],'operator_id'=>['nullable','integer','exists:operators,id'],'classification_id'=>['nullable','integer'],'per_page'=>['nullable','integer','min:1','max:100'],'page'=>['nullable','integer','min:1']];}
}
