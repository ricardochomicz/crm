<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Operator\SyncTenantOperatorsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\SyncTenantOperatorsRequest;
use Illuminate\Http\JsonResponse;

class SyncTenantOperatorsController extends Controller
{
    public function __invoke(
        SyncTenantOperatorsRequest $request,
        SyncTenantOperatorsAction $action
    ): JsonResponse {
        $action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->user()->id,
            $request->validated('operator_ids')
        );

        return response()->json(['message' => 'Operadoras da empresa atualizadas.']);
    }
}
