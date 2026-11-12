<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Commercial\ConsultantProductivityAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\TenantUser;
use App\Models\ConsultantMonthlyTarget;
use App\Enums\TenantUserRole;

class ConsultantProductivityController extends Controller
{
    public function __invoke(Request $request, string $tenantId, ConsultantProductivityAction $action): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'team_id' => ['sometimes', 'uuid'],
            'consultant_id' => ['sometimes', 'uuid'],
        ]);
        abort_if(\Carbon\Carbon::parse($data['from'])->diffInDays(\Carbon\Carbon::parse($data['to'])) > 366, 422, 'O período máximo é de 366 dias.');
        $tenant = $request->attributes->get('active_tenant');
        return response()->json($action->overview($tenant, $request->user(), $data));
    }
    public function saveMonthlyTarget(Request $request, string $tenantId): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $actor = TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();
        abort_unless(in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);

        $data = $request->validate([
            'tenant_user_id' => ['required', 'uuid'],
            'month' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
        abort_unless(substr($data['month'], 8) === '01', 422, 'Informe o primeiro dia do mês.');
        TenantUser::query()->where('tenant_id', $tenant->id)->where('status', 'ACTIVE')
            ->whereKey($data['tenant_user_id'])->firstOrFail();

        $target = ConsultantMonthlyTarget::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'tenant_user_id' => $data['tenant_user_id'], 'month' => $data['month']],
            ['amount' => $data['amount']],
        );
        return response()->json(['data' => [
            'id' => $target->id,
            'tenant_user_id' => $target->tenant_user_id,
            'month' => $target->month->format('Y-m-d'),
            'amount' => (float) $target->amount,
        ]]);
    }

    public function goalConfigurations(Request $request, string $tenantId, ConsultantProductivityAction $action): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'consultant_id' => ['sometimes', 'uuid'],
        ]);
        abort_if(\Carbon\Carbon::parse($data['from'])->diffInDays(\Carbon\Carbon::parse($data['to'])) > 366, 422);
        return response()->json($action->goalConfigurations($request->attributes->get('active_tenant'), $request->user(), $data));
    }

    public function records(Request $request, string $tenantId, ConsultantProductivityAction $action): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'consultant_id' => ['required', 'uuid'],
            'type' => ['required', 'in:activities,opportunities,orders'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        abort_if(\Carbon\Carbon::parse($data['from'])->diffInDays(\Carbon\Carbon::parse($data['to'])) > 366, 422);
        $tenant = $request->attributes->get('active_tenant');
        return response()->json($action->records($tenant, $request->user(), $data));
    }

}
