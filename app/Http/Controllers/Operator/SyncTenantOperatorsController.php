<?php

namespace App\Http\Controllers\Operator;

use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncTenantOperatorsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operator_ids' => ['required', 'array'],
            'operator_ids.*' => ['required', 'integer', 'distinct', 'exists:operators,id'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        DB::transaction(function () use ($tenant, $request, $data): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $membership = $tenant->users()->where('users.id', $request->user()->id)->first();
            abort_unless($membership && $membership->membership->role === TenantUserRole::Owner, 403);

            $ids = $data['operator_ids'];
            $selectedInactive = $tenant->operators()->where('is_active', false)->pluck('operators.id')->all();
            $invalid = Operator::query()->whereIn('id', $ids)->where('is_active', false)->whereNotIn('id', $selectedInactive)->exists();
            if ($invalid) {
                throw ValidationException::withMessages(['operator_ids' => 'Não é possível adicionar uma operadora inativa.']);
            }

            $tenant->operators()->sync($ids);
        });

        return response()->json(['message' => 'Operadoras da empresa atualizadas.']);
    }
}
