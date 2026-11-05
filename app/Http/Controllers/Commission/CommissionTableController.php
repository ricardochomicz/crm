<?php
namespace App\Http\Controllers\Commission;
use App\Http\Controllers\Controller;
use App\Models\CommissionTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class CommissionTableController extends Controller {
 public function __invoke(Request $request, string $tenantId, ?string $commissionId = null) {
  $tenant = $request->attributes->get('active_tenant');
  $write = $request->isMethod('POST') || $request->isMethod('PATCH');
  abort_unless($request->user()->can($write ? 'manageCommissions' : 'viewCommissions', $tenant), 403);
  $query = CommissionTable::query()->where('tenant_id', $tenant->id);
  if (!$write) return ['data' => $query->with('operator:id,name')->orderBy('name')->get(), 'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)]];
  $data = $request->validate(['name' => 'required|string|max:180',
   'operator_id' => ['required','integer',Rule::exists('tenant_operators','operator_id')->where('tenant_id',$tenant->id)],
   'percentage' => 'required|numeric|min:0|max:9999.99|decimal:0,2',
   'deduction_percentage' => 'nullable|numeric|min:0|max:100|decimal:0,2',
   'is_active' => 'sometimes|boolean']);
  if ($request->isMethod('POST')) return ['data' => $tenant->commissionTables()->create($data)];
 }
}
