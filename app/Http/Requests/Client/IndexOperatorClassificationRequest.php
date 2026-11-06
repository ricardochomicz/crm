<?php
namespace App\Http\Requests\Client;
use Illuminate\Foundation\Http\FormRequest;
class IndexOperatorClassificationRequest extends FormRequest
{
 public function authorize(): bool
 {
  $tenant = $this->attributes->get('active_tenant');
  return $tenant ? (bool) $this->user()?->can('viewCommissions', $tenant) : (bool) $this->user()?->is_platform_admin;
 }
 public function rules(): array
 {
  return ['search'=>['nullable','string','max:255'],'operator_id'=>['nullable','integer','exists:operators,id'],'is_active'=>['sometimes','boolean']];
 }
}
