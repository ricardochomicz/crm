<?php

namespace App\Http\Controllers\Commercial;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Actions\Commercial\ListConsultantRenewalPrioritiesAction;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConsultantPrioritiesController extends Controller
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    private function context(Request $request): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant, 403);

        $membership = $this->permissions->membership($request->user(), $tenant);
        abort_unless($membership, 403);

        return [$tenant, $membership];
    }

    public function index(Request $request, string $tenantId, ListConsultantRenewalPrioritiesAction $renewalAction): JsonResponse
    {
        [$tenant, $membership] = $this->context($request);
        $start = now()->startOfWeek()->toDateString();
        $end = now()->endOfWeek()->toDateString();
        $today = now()->toDateString();

        $query = Opportunity::query()
            ->where('tenant_id', $tenant->id)
            ->where('tenant_user_id', $membership->id)
            ->where('status', 'open')
            ->where(function ($builder) use ($start, $end): void {
                $builder->whereBetween('expected_close_at', [$start, $end])
                    ->orWhereDate('expected_close_at', '<', $start);
            });

        $opportunities = $query
            ->with(['client:id,company_name', 'operator:id,name'])
            ->withSum('items as total_lines', 'quantity')
            ->orderBy('expected_close_at')
            ->get();

        $acknowledged = DB::table('commercial_priority_acknowledgements')
            ->where('tenant_id', $tenant->id)
            ->where('tenant_user_id', $membership->id)
            ->where('acknowledged_on', $today)
            ->whereIn('opportunity_id', $opportunities->pluck('id'))
            ->pluck('opportunity_id')
            ->all();

        $items = $opportunities->map(static function (Opportunity $opportunity) use ($acknowledged): array {
            return [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'created_at' => $opportunity->created_at?->toIso8601String(),
                'client_name' => $opportunity->client?->company_name,
                'operator_name' => $opportunity->operator?->name,
                'expected_close_at' => $opportunity->expected_close_at?->toDateString(),
                'lines' => (int) ($opportunity->total_lines ?? 0),
                'monthly' => (string) $opportunity->estimated_monthly_amount,
                'acknowledged' => in_array($opportunity->id, $acknowledged, true),
            ];
        });

        $renewals = $renewalAction->execute($tenant->id, $membership->id, $request->user()->id);
        $renewalAcknowledged = DB::table('commercial_renewal_acknowledgements')
            ->where('tenant_id', $tenant->id)
            ->where('tenant_user_id', $membership->id)
            ->where('acknowledged_on', $today)
            ->pluck('client_contract_id')
            ->all();
        $renewals['items'] = array_map(static function (array $item) use ($renewalAcknowledged): array {
            $item['acknowledged'] = in_array($item['id'], $renewalAcknowledged, true);
            return $item;
        }, $renewals['items']);

        return response()->json(['data' => [
            'period' => ['from' => $start, 'to' => $end],
            'total_opportunities' => $items->count(),
            'total_lines' => $items->sum('lines'),
            'total_monthly' => number_format($items->sum(fn ($item) => (float) $item['monthly']), 2, '.', ''),
            'items' => $items->values()->all(),
            'renewals' => $renewals,
        ]]);
    }

    public function acknowledgeAll(
        Request $request,
        string $tenantId,
        ListConsultantRenewalPrioritiesAction $renewalAction
    ): JsonResponse {
        [$tenant, $membership] = $this->context($request);
        $data = $request->validate([
            'type' => ['required', 'in:opportunities,renewals'],
        ]);

        if ($data['type'] === 'renewals') {
            $renewals = $renewalAction->execute($tenant->id, $membership->id, $request->user()->id);
            abort_if($renewals['truncated'], 409, 'A lista de renovações está incompleta. Confirme individualmente.');
            $ids = array_column($renewals['items'], 'id');
            $table = 'commercial_renewal_acknowledgements';
            $column = 'client_contract_id';
        } else {
            $ids = Opportunity::query()
                ->where('tenant_id', $tenant->id)
                ->where('tenant_user_id', $membership->id)
                ->where('status', 'open')
                ->where(function ($query): void {
                    $query->whereDate('expected_close_at', '<', now()->startOfWeek()->toDateString())
                        ->orWhereBetween('expected_close_at', [
                            now()->startOfWeek()->toDateString(),
                            now()->endOfWeek()->toDateString(),
                        ]);
                })
                ->pluck('id')
                ->all();
            $table = 'commercial_priority_acknowledgements';
            $column = 'opportunity_id';
        }

        $now = now();
        $rows = array_map(static fn (string $id): array => [
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $membership->id,
            $column => $id,
            'acknowledged_on' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $ids);

        if ($rows) {
            DB::table($table)->upsert(
                $rows,
                ['tenant_id', 'tenant_user_id', $column, 'acknowledged_on'],
                ['updated_at']
            );
        }

        return response()->json(['data' => ['acknowledged' => count($ids)]]);
    }

    public function acknowledgeRenewal(
        Request $request,
        string $tenantId,
        string $contractId,
        ListConsultantRenewalPrioritiesAction $renewalAction
    ): JsonResponse {
        [$tenant, $membership] = $this->context($request);
        $renewals = $renewalAction->execute($tenant->id, $membership->id, $request->user()->id);
        abort_unless(in_array($contractId, array_column($renewals['items'], 'id'), true), 404);

        DB::table('commercial_renewal_acknowledgements')->updateOrInsert(
            [
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $membership->id,
                'client_contract_id' => $contractId,
                'acknowledged_on' => now()->toDateString(),
            ],
            ['updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['data' => ['acknowledged' => true]]);
    }

    public function acknowledge(Request $request, string $tenantId, string $opportunityId): JsonResponse
    {
        [$tenant, $membership] = $this->context($request);

        $exists = Opportunity::query()
            ->where('tenant_id', $tenant->id)
            ->where('tenant_user_id', $membership->id)
            ->where('status', 'open')
            ->whereKey($opportunityId)
            ->exists();

        abort_unless($exists, 404);

        DB::table('commercial_priority_acknowledgements')->updateOrInsert(
            [
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $membership->id,
                'opportunity_id' => $opportunityId,
                'acknowledged_on' => now()->toDateString(),
            ],
            ['updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['data' => ['acknowledged' => true]]);
    }
}
