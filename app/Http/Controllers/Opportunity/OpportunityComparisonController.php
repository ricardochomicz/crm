<?php

namespace App\Http\Controllers\Opportunity;

use App\Actions\Opportunity\OpportunityAction;
use App\Http\Controllers\Controller;
use App\Models\OpportunityComparison;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpportunityComparisonController extends Controller
{
    private function tenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant, 403);
        return $tenant;
    }

    public function index(Request $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()->can('viewOpportunities', $tenant), 403);
        $action->find($tenant, $request->user(), $opportunityId);
        return response()->json(['data' => OpportunityComparison::query()
            ->where('tenant_id', $tenant->id)->where('opportunity_id', $opportunityId)
            ->with('options')->latest()->get()->map(fn ($comparison) => $this->present($comparison))]);
    }

    public function store(Request $request, string $tenantId, string $opportunityId, OpportunityAction $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()->can('updateOpportunities', $tenant), 403);
        $opportunity = $action->find($tenant, $request->user(), $opportunityId);
        abort_unless($opportunity->status === 'open', 422, 'A oportunidade precisa estar aberta.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*.product_id' => ['required', 'uuid', 'distinct'],
            'options.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'options.*.monthly_unit_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'options.*.one_time_unit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'options.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $ids = collect($data['options'])->pluck('product_id');
        $products = Product::query()->where('tenant_id', $tenant->id)->where('is_active', true)
            ->whereIn('id', $ids)->get()->keyBy('id');
        if ($products->count() !== $ids->count()) {
            throw ValidationException::withMessages(['options' => 'Selecione apenas planos ativos desta empresa.']);
        }
        $operatorIds = $products->pluck('operator_id')->unique();
        if ($operatorIds->count() !== $ids->count()) {
            throw ValidationException::withMessages(['options' => 'Selecione somente um plano por operadora neste comparativo.']);
        }
        $allowed = $tenant->operators()->whereIn('operators.id', $operatorIds)->pluck('operators.id')->map(fn ($id) => (int) $id);
        if ($allowed->count() !== $operatorIds->count()) {
            throw ValidationException::withMessages(['options' => 'Há operadoras não vinculadas à empresa.']);
        }
        $names = Operator::query()->whereIn('id', $operatorIds)->pluck('name', 'id');
        $comparison = DB::transaction(function () use ($tenant, $opportunity, $data, $products, $names) {
            $comparison = OpportunityComparison::query()->create([
                'tenant_id' => $tenant->id,
                'opportunity_id' => $opportunity->id,
                'title' => $data['title'],
                'public_token' => Str::random(64),
                'expires_at' => now()->addDays(30),
            ]);
            foreach ($data['options'] as $option) {
                $product = $products[$option['product_id']];
                $comparison->options()->create([
                    'operator_id' => $product->operator_id,
                    'product_id' => $product->id,
                    'operator_name' => $names[$product->operator_id],
                    'product_name' => $product->name,
                    'quantity' => $option['quantity'],
                    'monthly_unit_amount' => $option['monthly_unit_amount'],
                    'one_time_unit_amount' => $option['one_time_unit_amount'] ?? 0,
                    'notes' => $option['notes'] ?? null,
                ]);
            }
            return $comparison->load('options');
        });
        return response()->json(['data' => $this->present($comparison)], 201);
    }

    public function revoke(Request $request, string $tenantId, string $opportunityId, string $comparisonId, OpportunityAction $action): JsonResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($request->user()->can('updateOpportunities', $tenant), 403);
        $action->find($tenant, $request->user(), $opportunityId);
        $comparison = OpportunityComparison::query()->where('tenant_id', $tenant->id)
            ->where('opportunity_id', $opportunityId)->findOrFail($comparisonId);
        $comparison->update(['revoked_at' => now()]);
        return response()->json(['data' => $this->present($comparison->load('options'))]);
    }

    public function publicShow(string $token): JsonResponse
    {
        $comparison = OpportunityComparison::query()->where('public_token', $token)
            ->whereNull('revoked_at')->where('expires_at', '>', now())
            ->with('options')->firstOrFail();
        return response()->json(['data' => [
            'title' => $comparison->title,
            'created_at' => $comparison->created_at,
            'expires_at' => $comparison->expires_at,
            'options' => $comparison->options->map(fn ($option) => [
                'operator_name' => $option->operator_name,
                'product_name' => $option->product_name,
                'quantity' => $option->quantity,
                'monthly_unit_amount' => $option->monthly_unit_amount,
                'one_time_unit_amount' => $option->one_time_unit_amount,
                'notes' => $option->notes,
            ]),
        ]]]);
    }

    private function present(OpportunityComparison $comparison): array
    {
        return [
            'id' => $comparison->id,
            'title' => $comparison->title,
            'created_at' => $comparison->created_at,
            'expires_at' => $comparison->expires_at,
            'revoked_at' => $comparison->revoked_at,
            'public_token' => $comparison->public_token,
            'options' => $comparison->options,
        ];
    }
}
