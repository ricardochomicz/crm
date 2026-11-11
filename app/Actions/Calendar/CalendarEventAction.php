<?php

namespace App\Actions\Calendar;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Models\CalendarEvent;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarEventAction
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    private function member(User $user, Tenant $tenant): TenantUser
    {
        return $this->permissions->membership($user, $tenant) ?? abort(403);
    }

    private function assignee(Tenant $tenant, string $id): TenantUser
    {
        return TenantUser::query()->where('tenant_id', $tenant->getKey())
            ->where('status', 'ACTIVE')->findOrFail($id);
    }

    private function prepare(Tenant $tenant, TenantUser $actor, array $data): array
    {
        $assigneeId = $data['tenant_user_id'] ?? $actor->getKey();
        if ($assigneeId !== $actor->getKey() && ! $actor->user->can('viewTeamCalendar', $tenant)) abort(403);
        $this->assignee($tenant, $assigneeId);
        if (! empty($data['client_id']) && ! DB::table('clients')->where('tenant_id', $tenant->getKey())->where('id', $data['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Cliente não pertence a esta empresa.']);
        }
        if (! empty($data['opportunity_id'])) {
            $opportunity = Opportunity::query()->where('tenant_id', $tenant->getKey())->findOrFail($data['opportunity_id']);
            if ($opportunity->tenant_user_id !== $assigneeId && ! $actor->user->can('viewTeamCalendar', $tenant)) abort(403);
            if (! empty($data['client_id']) && $data['client_id'] !== $opportunity->client_id) {
                throw ValidationException::withMessages(['client_id' => 'O cliente deve ser o mesmo da oportunidade.']);
            }
            $data['client_id'] = $opportunity->client_id;
        }
        $data['tenant_user_id'] = $assigneeId;
        return $data;
    }

    private function checkDates(array $data, bool $enforceFuture = false): void
    {
        $start = Carbon::parse($data['starts_at']);
        if ($enforceFuture && $start->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['starts_at' => 'Escolha uma data e horário futuros.']);
        }
        if (Carbon::parse($data['ends_at'])->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ends_at' => 'O término deve ser posterior ao início.']);
        }
    }

    public function list(Tenant $tenant, User $user, array $filters): Collection
    {
        $actor = $this->member($user, $tenant);
        $team = ($filters['scope'] ?? 'mine') === 'team';
        if ($team && ! $user->can('viewTeamCalendar', $tenant)) abort(403);
        $isManager = in_array($actor->role->value, ['OWNER', 'MANAGER'], true);
        $supervisedTeamIds = $actor->supervisedTeams()->where('tenant_id', $tenant->getKey())->pluck('id');
        return CalendarEvent::query()->where('tenant_id', $tenant->getKey())
            ->when(! $team, fn ($q) => $q->where('tenant_user_id', $actor->getKey()))
            ->when($team && ! $isManager, function ($q) use ($tenant, $supervisedTeamIds, $actor) {
                if ($supervisedTeamIds->isNotEmpty()) {
                    $memberIds = TenantUser::query()->where('tenant_id', $tenant->getKey())
                        ->whereIn('team_id', $supervisedTeamIds)->where('status', 'ACTIVE')->pluck('id');
                    $q->whereIn('tenant_user_id', $memberIds);
                } else {
                    $q->where('tenant_user_id', $actor->getKey());
                }
            })
            ->when(! empty($filters['opportunity_id']), fn ($q) => $q->where('opportunity_id', $filters['opportunity_id']))
            ->where('starts_at', '<', $filters['to'])
            ->where('ends_at', '>', $filters['from'])
            ->orderBy('starts_at')->limit(1000)->get();
    }

    /** Paginated task inbox; shares the same tenant and team visibility rules as the calendar. */
    public function activities(Tenant $tenant, User $user, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $actor = $this->member($user, $tenant);
        $team = ($filters['scope'] ?? 'mine') === 'team';
        if ($team && ! $user->can('viewTeamCalendar', $tenant)) abort(403);

        $isManager = in_array($actor->role->value, ['OWNER', 'MANAGER'], true);
        $query = CalendarEvent::query()
            ->with(['client', 'opportunity.client', 'assignee.user'])
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', ['task', 'reminder']);

        if (! $team) {
            $query->where('tenant_user_id', $actor->getKey());
        } elseif (! $isManager) {
            $supervised = $actor->supervisedTeams()->where('tenant_id', $tenant->getKey())->pluck('id');
            $members = TenantUser::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('team_id', $supervised)->where('status', 'ACTIVE')->pluck('id');
            $query->whereIn('tenant_user_id', $members->push($actor->getKey())->unique());
        }

        $status = $filters['status'] ?? 'pending';
        if ($status === 'overdue') {
            $query->where('status', 'pending')->where('ends_at', '<', now());
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('client', fn ($client) => $client->where('company_name', 'like', $term))
                    ->orWhereHas('opportunity', fn ($opportunity) => $opportunity->where('title', 'like', $term));
            });
        }
        return $query->orderBy('ends_at')->orderBy('id')
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100));
    }

    public function create(Tenant $tenant, User $user, array $data): CalendarEvent
    {
        $actor = $this->member($user, $tenant);
        $data['status'] = $data['status'] ?? 'pending';
        $data['priority'] = $data['priority'] ?? 'normal';
        $data = $this->prepare($tenant, $actor, $data);
        $this->checkDates($data, true);
        return CalendarEvent::create([...$data, 'tenant_id' => $tenant->getKey(), 'created_by_tenant_user_id' => $actor->getKey()]);
    }

    private function owned(Tenant $tenant, User $user, string $id): CalendarEvent
    {
        $actor = $this->member($user, $tenant);
        return CalendarEvent::query()->where('tenant_id', $tenant->getKey())
            ->where('tenant_user_id', $actor->getKey())->findOrFail($id);
    }

    public function update(Tenant $tenant, User $user, string $id, array $data): CalendarEvent
    {
        $event = $this->owned($tenant, $user, $id);
        $actor = $this->member($user, $tenant);
        $data = $this->prepare($tenant, $actor, [...$event->only(['tenant_user_id', 'client_id', 'opportunity_id']), ...$data]);
        $datesChanged = (array_key_exists('starts_at', $data) && ! Carbon::parse($data['starts_at'])->equalTo($event->starts_at))
            || (array_key_exists('ends_at', $data) && ! Carbon::parse($data['ends_at'])->equalTo($event->ends_at));
        $this->checkDates([...$event->only(['starts_at', 'ends_at']), ...$data], $datesChanged);
        if (($data['status'] ?? null) === 'completed' && $event->status !== 'completed') {
            $data['completed_at'] = now();
        } elseif (isset($data['status']) && $data['status'] !== 'completed' && $event->status === 'completed') {
            $data['completed_at'] = null;
        }
        $event->update($data);
        return $event->refresh();
    }

    public function delete(Tenant $tenant, User $user, string $id): void
    {
        $this->owned($tenant, $user, $id)->delete();
    }
}
