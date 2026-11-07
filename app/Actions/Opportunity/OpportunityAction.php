<?php

namespace App\Actions\Opportunity;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Opportunity;
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
        return $query->with(['client', 'stage', 'operator'])->latest()->paginate(20);
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

    public function create(Tenant $tenant, User $user, array $data): Opportunity
    {
        $actor = $this->actor($tenant, $user);
        $this->validateRelations($tenant, $data);
        $assignee = $data['tenant_user_id'] ?? $actor->id;
        if ($assignee !== $actor->id && ! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            $member = TenantUser::query()->where('tenant_id', $tenant->id)->findOrFail($assignee);
            abort_unless(DB::table('teams')->where('tenant_id', $tenant->id)
                ->where('supervisor_membership_id', $actor->id)->where('id', $member->team_id)->exists(), 403);
        }
        abort_unless(TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('status', TenantUserStatus::Active->value)->whereKey($assignee)->exists(), 422);
        return DB::transaction(function () use ($tenant, $data, $assignee) {
            return Opportunity::create([...$data, 'tenant_id' => $tenant->id, 'tenant_user_id' => $assignee]);
        });
    }

    public function update(Tenant $tenant, User $user, string $id, array $data): Opportunity
    {
        $actor = $this->actor($tenant, $user);
        $event = $this->find($tenant, $user, $id);
        abort_unless($event->tenant_user_id === $actor->id || in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true), 403);
        abort_unless($event->status === 'open', 422, 'Somente oportunidades abertas podem ser editadas.');
        $this->validateRelations($tenant, $data);
        $event->update($data);
        return $event->refresh();
    }
}
