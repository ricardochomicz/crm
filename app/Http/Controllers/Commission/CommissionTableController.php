<?php
namespace App\Http\Controllers\Commission;
use App\Http\Controllers\Controller;
use App\Models\CommissionTable;
use App\Enums\OrderType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
class CommissionTableController extends Controller {
 public function __invoke(Request $request, string $tenantId, ?string $commissionId = null) {
  $tenant = $request->attributes->get('active_tenant');
  $write = !$request->isMethod('GET');
  abort_unless($request->user()->can($write ? 'manageCommissions' : 'viewCommissions', $tenant), 403);
  if ($commissionId === 'settings' && $request->isMethod('GET')) {
   return ['data' => ['default_deduction_percentage' => DB::table('tenant_commission_settings')->where('tenant_id', $tenant->id)->value('default_deduction_percentage') ?? '0.00']];
  }
  if ($commissionId === 'settings' && $request->isMethod('PUT')) {
   $input = $request->validate(['default_deduction_percentage' => 'required|numeric|min:0|max:100']);
   DB::table('tenant_commission_settings')->updateOrInsert(['tenant_id' => $tenant->id], ['default_deduction_percentage' => $input['default_deduction_percentage']]);
   return ['data' => $input];
  }
  if ($commissionId === 'order-types' && $request->isMethod('GET')) return ['data' => OrderType::options()];
  $query = CommissionTable::query()->where('tenant_id', $tenant->id);
  if (!$write) return ['data' => $query->with('operator:id,name')->orderBy('order_type')->get(), 'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)]];
  $data = $request->validate(['order_type' => ['required', Rule::enum(OrderType::class)],
   'operator_id' => ['required','integer',Rule::exists('tenant_operators','operator_id')->where('tenant_id',$tenant->id)],
   'percentage' => 'required|numeric|min:0|max:9999.99|decimal:0,2',
   'deduction_percentage' => 'nullable|numeric|min:0|max:100|decimal:0,2',
   'is_active' => 'sometimes|boolean', 'description' => 'nullable|string|max:10000']);
  $type = OrderType::from($data['order_type']);
  $data['name'] = $type->label();
  $duplicate = $query->where('operator_id', $data['operator_id'])->where('order_type', $data['order_type']);
  if ($commissionId) $duplicate->where('id', '!=', $commissionId);
  if ($duplicate->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['order_type' => 'Já existe comissão para esta operadora e tipo de pedido.']);
  if ($request->isMethod('POST')) return ['data' => $tenant->commissionTables()->create($data)];
  $record = $query->findOrFail($commissionId);
  $record->update($data);
  return ['data' => $record->fresh()];
 }
}
