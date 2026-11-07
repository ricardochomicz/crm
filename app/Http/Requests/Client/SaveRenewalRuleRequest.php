<?php
namespace App\Http\Requests\Client;
use Illuminate\Foundation\Http\FormRequest;
class SaveRenewalRuleRequest extends FormRequest {
 public function authorize():bool {
  if(str_contains((string)$this->path(),'/admin/'))return (bool)$this->user()?->is_platform_admin;
  $tenant=$this->attributes->get('active_tenant');
  return $tenant && (bool) $this->user()?->can('manageOperatorClassifications', $tenant);
 }
 public function rules():array {return ['minimum_months'=>['required','integer','min:0','max:1200'],'valid_from'=>['required','date_format:Y-m-d'],'valid_until'=>['nullable','date_format:Y-m-d','after_or_equal:valid_from'],'is_active'=>['required','boolean'],'notes'=>['nullable','string','max:500']];}
}
