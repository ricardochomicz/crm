<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Http\Controllers\Controller;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MyCommissionController extends Controller
{
    public function __invoke(Request $request, ResolveTenantPermissionsAction $permissions): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $member = $tenant ? $permissions->membership($request->user(), $tenant) : null;
        abort_unless($member !== null, 403);
        $this->authorize('viewMyCommissions', $tenant);

        $filters = Validator::make($request->query(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'in:ESTIMATED,ELIGIBLE,PENDING_RULES'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $query = DB::table('order_commission_accruals')
            ->where('tenant_id', $tenant->id)->where('is_current', true)
            ->whereBetween('sold_on', [$filters['from'], $filters['to']])
            // Narrow the SQL scan; the authoritative identity check remains below.
            ->where('participant_amounts', 'like', '%'.$member->id.'%')
            ->orderByDesc('sold_on')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $typeNames = DB::table('order_types')->pluck('name', 'id');
        $totals = ['ESTIMATED' => '0.00', 'ELIGIBLE' => '0.00'];
        $rows = [];
        foreach ($query->cursor() as $accrual) {
            $participants = json_decode($accrual->participant_amounts, true, flags: JSON_THROW_ON_ERROR);
            $amount = BigDecimal::zero();
            $matched = false;
            $percentage = null;
            $snapshot = json_decode($accrual->rule_snapshot ?? '{}', true, flags: JSON_THROW_ON_ERROR);
            foreach ($participants as $participant) {
                if (($participant['tenant_user_id'] ?? null) !== $member->id) {
                    continue;
                }
                $matched = true;
                $amount = $amount->plus((string) ($participant['amount'] ?? '0'));
                foreach (($snapshot['participants'] ?? []) as $ruleParticipant) {
                    if (($ruleParticipant['tenant_user_id'] ?? null) === $member->id
                        && ($ruleParticipant['role'] ?? null) === ($participant['role'] ?? null)) {
                        $percentage = $ruleParticipant['percentage'] ?? null;
                        break;
                    }
                }
            }
            if (! $matched) {
                continue;
            }

            $value = (string) $amount->toScale(2);
            if (isset($totals[$accrual->status])) {
                $totals[$accrual->status] = (string) BigDecimal::of($totals[$accrual->status])->plus($value)->toScale(2);
            }
            $rows[] = [
                'order_id' => $accrual->commercial_order_id,
                'sold_on' => $accrual->sold_on,
                'activated_on' => $accrual->activated_on,
                'line_number' => $accrual->line_number,
                'description' => $accrual->description,
                'order_type' => $typeNames[$accrual->order_type] ?? $accrual->order_type,
                'plan_amount' => $accrual->base_amount === null ? null : (string) BigDecimal::of($accrual->base_amount)->toScale(2),
                'commission_percentage' => $accrual->status === 'PENDING_RULES' ? null : $percentage,
                'status' => $accrual->status,
                'revision' => (int) $accrual->revision,
                'amount' => $accrual->status === 'PENDING_RULES' ? null : $value,
            ];
        }

        $page = (int) ($filters['page'] ?? 1);
        $perPage = 20;
        return response()->json(['data' => [
            'summary' => ['estimated' => $totals['ESTIMATED'], 'eligible' => $totals['ELIGIBLE']],
            'items' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => count($rows),
                'last_page' => max(1, (int) ceil(count($rows) / $perPage))],
            'note' => 'Valores de comissão apurados para o usuário autenticado. Elegibilidade não comprova pagamento.',
        ]]);
    }
}
