<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterCommissionTableAction;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\CommissionTableRequest;
use App\Http\Resources\Commission\CommissionTableResource;
use App\Models\CommissionTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionTableController extends Controller
{
    public function __invoke(CommissionTableRequest $request, string $tenantId, FilterCommissionTableAction $filter, ?string $commissionId = null): array
    {
        $tenant = $request->attributes->get('active_tenant');
        $write = ! $request->isMethod('GET');
        abort_unless($request->user()->can($write ? 'manageCommissions' : 'viewCommissions', $tenant), 403);
        if ($commissionId === 'settings' && $request->isMethod('GET')) {
            return ['data' => ['default_deduction_percentage' => DB::table('tenant_commission_settings')->where('tenant_id', $tenant->id)->value('default_deduction_percentage') ?? '0.00']];
        }
        if ($commissionId === 'settings' && $request->isMethod('PUT')) {
            $input = $request->validated();
            DB::table('tenant_commission_settings')->updateOrInsert(['tenant_id' => $tenant->id], ['default_deduction_percentage' => $input['default_deduction_percentage']]);

            return ['data' => $input];
        }
        if ($commissionId === 'order-types' && $request->isMethod('GET')) {
            return ['data' => OrderType::options()];
        }
        $query = CommissionTable::query()->where('tenant_id', $tenant->id);
        if (! $write) {
            return ['data' => CommissionTableResource::collection($filter->execute($tenant->id, $request->validated())), 'meta' => ['can_manage' => $request->user()->can('manageCommissions', $tenant)]];
        }
        $data = $request->validated();
        $type = OrderType::from($data['order_type']);
        $data['name'] = $type->label();
        $duplicate = (clone $query)->where('operator_id', $data['operator_id'])->where('order_type', $data['order_type']);
        if ($commissionId) {
            $duplicate->where('id', '!=', $commissionId);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['order_type' => 'Já existe comissão para esta operadora e tipo de pedido.']);
        }
        if ($request->isMethod('POST')) {
            return ['data' => new CommissionTableResource($tenant->commissionTables()->create($data))];
        }
        $record = $query->findOrFail($commissionId);
        $record->update($data);

        return ['data' => new CommissionTableResource($record->fresh())];
    }
}
