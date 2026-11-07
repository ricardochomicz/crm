<?php

namespace App\Actions\Opportunity;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    private function actor(Tenant $tenant, User $user): TenantUser
    {
        return $this->permissions->membership($user, $tenant) ?? abort(403);
    }

    private function visible(Builder $query, Tenant $tenant, TenantUser $actor): Builder
    {
        $query->where('tenant_id', $tenant->id);
        if (in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) return $query;

        $teams = DB::table('teams')->where('tenant_id', $tenant->id)
            ->where('supervisor_membership_id', $actor->id)->pluck('id');

        return $query->where(function (Builder $q) use ($actor, $teams, $tenant) {
            $q->where('tenant_user_id', $actor->id);
            if ($teams->isNotEmpty()) {
                $q->orWhereIn('tenant_user_id', TenantUser::query()
                    ->where('tenant_id', $tenant->id)->whereIn('team_id', $teams)->select('id'));
            }
        });
    }

    public function list(Tenant $tenant, User $user, array $filters)
    {
        $query = $this->visible(Opportunity::query(), $tenant, $this->actor($tenant, $user));
        $query->where('status', $filters['status'] ?? 'open');
        if (! empty($filters['search'])) {
            $term = addcslashes(trim($filters['search']), '%_\\');
            $query->where(fn (Builder $q) => $q->where('title', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
        }
        if (! empty($filters['funnel_stage_id'])) $query->where('funnel_stage_id', $filters['funnel_stage_id']);
        return $query->with(['client', 'stage', 'operator', 'items'])->latest()->paginate(20);
    }

    public function find(Tenant $tenant, User $user, string $id): Opportunity
    {
        return $this->visible(Opportunity::query(), $tenant, $this->actor($tenant, $user))
            ->with(['items', 'client', 'stage', 'operator'])->findOrFail($id);
    }

    private function validateRelations(Tenant $tenant, array $data): void
    {
        foreach (['client_id' => 'clients', 'funnel_stage_id' => 'opportunity_funnel_stages'] as $key => $table) {
            if (! empty($data[$key]) && ! DB::table($table)->where('tenant_id', $tenant->id)->where('id', $data[$key])->exists()) {
                throw ValidationException::withMessages([$key => 'Registro não pertence à empresa.']);
            }
        }
        if (! empty($data['operator_id']) && ! DB::table('tenant_operators')
            ->where('tenant_id', $tenant->id)->where('operator_id', $data['operator_id'])->exists()) {
            throw ValidationException::withMessages(['operator_id' => 'Operadora não vinculada à empresa.']);
        }
    }

    private function validatedItems(Tenant $tenant, array $items): array
    {
        $resolved = [];
        $numbers = [];
        foreach ($items as $index => $item) {
            $product = Product::query()->where('tenant_id', $tenant->id)->where('is_active', true)
                ->find($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(["items.$index.product_id" => 'Selecione um plano ativo da empresa.']);
            }
            $number = preg_replace('/\\D/', '', $item['line_number'] ?? '');
            if (($item['operation_type'] ?? null) === 'portability' && $number === '') {
                throw ValidationException::withMessages(["items.$index.line_number" => 'Portabilidade exige número com DDD.']);
            }
            if ($number !== '' && ! in_array(strlen($number), [10, 11], true)) {
                throw ValidationException::withMessages(["items.$index.line_number" => 'Informe um número com DDD válido.']);
            }
            if ($number !== '' && in_array($number, $numbers, true)) {
                throw ValidationException::withMessages(["items.$index.line_number" => 'Esta linha já foi adicionada.']);
            }
            if ($number !== '') $numbers[] = $number;
            if ($number !== '' && (int) $item['quantity'] !== 1) {
                throw ValidationException::withMessages(["items.$index.quantity" => 'Uma linha identificada deve ter quantidade 1.']);
            }
            $resolved[] = [
                'product_id' => $product->id, 'operator_id' => $product->operator_id,
                'line_number' => $number ?: null, 'operation_type' => $item['operation_type'], 'description' => $item['description'],
                'quantity' => $item['quantity'], 'monthly_unit_amount' => $item['monthly_unit_amount'],
                'one_time_unit_amount' => $item['one_time_unit_amount'] ?? 0, 'notes' => $item['notes'] ?? null,
            ];
        }
        return $resolved;
    }

    private function totals(array $items): array
    {
        $monthly = 0;
        $once = 0;
        foreach ($items as $item) {
            $monthly += (int) round((float) $item['monthly_unit_amount'] * 100) * $item['quantity'];
            $once += (int) round((float) $item['one_time_unit_amount'] * 100) * $item['quantity'];
        }
        return ['estimated_monthly_amount' => number_format($monthly / 100, 2, '.', ''),
            'estimated_one_time_amount' => number_format($once / 100, 2, '.', '')];
    }

    public function create(Tenant $tenant, User $user, array $data): Opportunity
    {
        $actor = $this->actor($tenant, $user);
        $this->validateRelations($tenant, $data);
        $items = $this->validatedItems($tenant, $data['items']);
        unset($data['items']);
        $data = [...$data, ...$this->totals($items)];
        $assignee = $data['tenant_user_id'] ?? $actor->id;
        if ($assignee !== $actor->id && ! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $member = TenantUser::query()->where('tenant_id', $tenant->id)->findOrFail($assignee);
            abort_unless(DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->where('id', $member->team_id)->exists(), 403);
        }
        abort_unless(TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('status', TenantUserStatus::Active->value)->whereKey($assignee)->exists(), 422);
        return DB::transaction(function () use ($tenant, $data, $assignee, $items) {
            $opportunity = Opportunity::create([...$data, 'tenant_id' => $tenant->id, 'tenant_user_id' => $assignee]);
            $opportunity->items()->createMany($items);
            return $opportunity->load('items');
        });
    }

    public function update(Tenant $tenant, User $user, string $id, array $data): Opportunity
    {
        $actor = $this->actor($tenant, $user);
        $event = $this->find($tenant, $user, $id);
        abort_unless($event->tenant_user_id === $actor->id || in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);
        abort_unless($event->status === 'open', 422, 'Somente oportunidades abertas podem ser editadas.');
        $this->validateRelations($tenant, $data);
        // Reassignment must go through an explicit, authorized workflow.
        unset($data['tenant_user_id']);
        $items = array_key_exists('items', $data) ? $this->validatedItems($tenant, $data['items']) : null;
        unset($data['items']);
        if ($items !== null) $data = [...$data, ...$this->totals($items)];
        return DB::transaction(function () use ($event, $data, $items) {
            $event->update($data);
            if ($items !== null) {
                $event->items()->delete();
                $event->items()->createMany($items);
            }
            return $event->refresh()->load('items');
        });
    }
}
