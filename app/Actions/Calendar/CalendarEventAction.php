<?php

namespace App\Actions\Calendar;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Events\CommercialDataChanged;
use App\Models\CalendarEvent;
use App\Models\CalendarEventHistory;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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


    /** Members the actor is authorized to assign activities to. */
    public function assignableMembers(Tenant $tenant, User $user): array
    {
        $actor = $this->member($user, $tenant);
        $query = TenantUser::query()->with('user')
            ->where('tenant_id', $tenant->getKey())->where('status', 'ACTIVE');

        if (! $user->can('viewTeamCalendar', $tenant)) {
            $query->whereKey($actor->getKey());
        } elseif (! in_array($actor->role->value, ['OWNER', 'MANAGER'], true)) {
            $teamIds = $actor->supervisedTeams()->where('tenant_id', $tenant->getKey())->pluck('id');
            $query->where(function ($members) use ($actor, $teamIds) {
                $members->whereKey($actor->getKey())->orWhereIn('team_id', $teamIds);
            });
        }

        return $query->get()->sortBy(fn ($member) => $member->user?->name ?? '')
            ->map(fn ($member) => ['id' => $member->id, 'name' => $member->user?->name ?? 'Membro'])
            ->values()->all();
    }

    private function assignee(Tenant $tenant, string $id): TenantUser
    {
        return TenantUser::query()->where('tenant_id', $tenant->getKey())
            ->where('status', 'ACTIVE')->findOrFail($id);
    }

    private function prepare(Tenant $tenant, TenantUser $actor, array $data): array
    {
        $assigneeId = $data['tenant_user_id'] ?? $actor->getKey();
        if ($assigneeId !== $actor->getKey()) {
            abort_unless($actor->user->can('viewTeamCalendar', $tenant), 403);
            $manager = in_array($actor->role->value, ['OWNER', 'MANAGER'], true);
            if (! $manager) {
                $teamIds = $actor->supervisedTeams()->where('tenant_id', $tenant->getKey())->pluck('id');
                abort_unless(TenantUser::query()->where('tenant_id', $tenant->getKey())
                    ->whereIn('team_id', $teamIds)->whereKey($assigneeId)->exists(), 403);
            }
        }
        $this->assignee($tenant, $assigneeId);
        if (! empty($data['client_id']) && ! DB::table('clients')->where('tenant_id', $tenant->getKey())->where('id', $data['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => 'Cliente não pertence a esta empresa.']);
        }
        if (! empty($data['opportunity_id'])) {
            $opportunity = Opportunity::query()->where('tenant_id', $tenant->getKey())->findOrFail($data['opportunity_id']);
            if ($opportunity->tenant_user_id !== $assigneeId && ! $actor->user->can('viewTeamCalendar', $tenant)) {
                abort(403);
            }
            if (! empty($data['client_id']) && $data['client_id'] !== $opportunity->client_id) {
                throw ValidationException::withMessages(['client_id' => 'O cliente deve ser o mesmo da oportunidade.']);
            }
            $data['client_id'] = $opportunity->client_id;
        }
        if (! empty($data['protocol_id'])) {
            $protocol = DB::table('after_sales_protocols')
                ->where('tenant_id', $tenant->getKey())->where('id', $data['protocol_id'])->first();
            if (! $protocol) {
                throw ValidationException::withMessages(['protocol_id' => 'Protocolo não encontrado nesta empresa.']);
            }
            if (! empty($data['client_id']) && $data['client_id'] !== $protocol->client_id) {
                throw ValidationException::withMessages(['client_id' => 'O cliente deve ser o mesmo do protocolo.']);
            }
            $data['client_id'] = $protocol->client_id;
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
        if ($team && ! $user->can('viewTeamCalendar', $tenant)) {
            abort(403);
        }
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
    public function activities(Tenant $tenant, User $user, array $filters): LengthAwarePaginator
    {
        $actor = $this->member($user, $tenant);
        $team = ($filters['scope'] ?? 'mine') === 'team';
        if ($team && ! $user->can('viewTeamCalendar', $tenant)) {
            abort(403);
        }

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


    /** Aggregate all visible tasks without depending on the current page or status filter. */
    public function activitiesOverview(Tenant $tenant, User $user, array $filters): array
    {
        $actor = $this->member($user, $tenant);
        $team = ($filters['scope'] ?? 'mine') === 'team';
        abort_if($team && ! $user->can('viewTeamCalendar', $tenant), 403);

        $query = CalendarEvent::query()->where('tenant_id', $tenant->getKey())
            ->whereIn('type', ['task', 'reminder']);

        if (! $team) {
            $query->where('tenant_user_id', $actor->getKey());
        } elseif (! in_array($actor->role->value, ['OWNER', 'MANAGER'], true)) {
            $teams = $actor->supervisedTeams()->where('tenant_id', $tenant->getKey())->pluck('id');
            $members = TenantUser::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('team_id', $teams)->where('status', 'ACTIVE')->pluck('id');
            $query->whereIn('tenant_user_id', $members->push($actor->getKey())->unique());
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

        $counts = $query->selectRaw("COUNT(*) AS total")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN status = 'pending' AND ends_at < ? THEN 1 ELSE 0 END) AS overdue", [now()])
            ->selectRaw("SUM(CASE WHEN status = 'pending' AND ends_at >= ? AND ends_at <= ? THEN 1 ELSE 0 END) AS due_soon", [now(), now()->addDay()])
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'overdue' => (int) ($counts->overdue ?? 0),
            'due_soon' => (int) ($counts->due_soon ?? 0),
            'completed' => (int) ($counts->completed ?? 0),
            'cancelled' => (int) ($counts->cancelled ?? 0),
        ];
    }

    public function create(Tenant $tenant, User $user, array $data): CalendarEvent
    {
        $actor = $this->member($user, $tenant);
        $data['status'] = $data['status'] ?? 'pending';
        $data['priority'] = $data['priority'] ?? 'normal';
        $data = $this->prepare($tenant, $actor, $data);
        $this->checkDates($data, true);

        return DB::transaction(function () use ($tenant, $actor, $data) {
            $event = CalendarEvent::create([...$data, 'tenant_id' => $tenant->getKey(), 'created_by_tenant_user_id' => $actor->getKey()]);
            $this->record($event, $actor, 'created', []);
            app(ActivityNotificationAction::class)->notify($event, 'assigned');
            DB::afterCommit(fn () => CommercialDataChanged::dispatch($event->tenant_id));
            return $event;
        });
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
        $data = $this->prepare($tenant, $actor, [...$event->only(['tenant_user_id', 'client_id', 'opportunity_id', 'protocol_id']), ...$data]);
        $datesChanged = (array_key_exists('starts_at', $data) && ! Carbon::parse($data['starts_at'])->equalTo($event->starts_at))
            || (array_key_exists('ends_at', $data) && ! Carbon::parse($data['ends_at'])->equalTo($event->ends_at));
        $this->checkDates([...$event->only(['starts_at', 'ends_at']), ...$data], $datesChanged);
        if (($data['status'] ?? null) === 'completed' && $event->status !== 'completed') {
            $data['completed_at'] = now();
        } elseif (isset($data['status']) && $data['status'] !== 'completed' && $event->status === 'completed') {
            $data['completed_at'] = null;
        }
        return DB::transaction(function () use ($event, $data, $actor) {
            $before = $event->only(['title', 'type', 'status', 'priority', 'tenant_user_id', 'client_id', 'opportunity_id', 'protocol_id', 'starts_at', 'ends_at']);
            $event->update($data);
            $after = $event->refresh()->only(array_keys($before));
            $changes = [];
            foreach ($before as $field => $value) {
                $previous = $value instanceof \DateTimeInterface ? $value->format('c') : $value;
                $current = $after[$field] instanceof \DateTimeInterface ? $after[$field]->format('c') : $after[$field];
                if ($previous != $current) $changes[$field] = ['from' => $previous, 'to' => $current];
            }
            if ($changes) {
                if (isset($changes['tenant_user_id'])) {
                    app(ActivityNotificationAction::class)->notify($event, 'assigned');
                }
                $action = isset($changes['tenant_user_id']) ? 'reassigned' : (isset($changes['status']) && $after['status'] === 'completed' ? 'completed' : 'updated');
                $this->record($event, $actor, $action, $changes);
                DB::afterCommit(fn () => CommercialDataChanged::dispatch($event->tenant_id));
            }
            return $event;
        });
    }

    private function record(CalendarEvent $event, TenantUser $actor, string $action, array $changes): void
    {
        CalendarEventHistory::create([
            'tenant_id' => $event->tenant_id,
            'calendar_event_id' => $event->id,
            'actor_tenant_user_id' => $actor->id,
            'action' => $action,
            'changes' => $changes ?: null,
        ]);
    }

    public function show(Tenant $tenant, User $user, string $id): CalendarEvent
    {
        return $this->owned($tenant, $user, $id)->load(['client', 'opportunity.client', 'assignee.user']);
    }

    public function history(Tenant $tenant, User $user, string $id): array
    {
        $event = $this->owned($tenant, $user, $id);
        return CalendarEventHistory::query()->with('actor.user')
            ->where('tenant_id', $tenant->id)->where('calendar_event_id', $event->id)
            ->orderByDesc('created_at')->limit(100)->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'changes' => $entry->changes,
                'actor_name' => $entry->actor?->user?->name ?? 'Usuário removido',
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->all();
    }

    public function delete(Tenant $tenant, User $user, string $id): void
    {
        $event = $this->owned($tenant, $user, $id);
        $actor = $this->member($user, $tenant);
        DB::transaction(function () use ($event, $actor) {
            $this->record($event, $actor, 'deleted', []);
            $event->delete();
            DB::afterCommit(fn () => CommercialDataChanged::dispatch($event->tenant_id));
        });
    }
}
