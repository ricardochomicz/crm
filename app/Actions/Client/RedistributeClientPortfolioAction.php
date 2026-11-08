<?php

namespace App\Actions\Client;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\ClientSeller;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RedistributeClientPortfolioAction
{
    public function authorize(Tenant $tenant, int $userId): TenantUser
    {
        $actor = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('status', TenantUserStatus::Active->value)
            ->firstOrFail();

        abort_unless(
            in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true),
            403,
            'Somente proprietários e gerentes podem redistribuir carteiras.'
        );

        abort_unless($tenant->subscription?->allowsOperationalWrites(), 403);

        return $actor;
    }

    public function overview(Tenant $tenant): array
    {
        $members = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->with('user:id,name')
            ->get();

        $counts = ClientSeller::query()
            ->select('tenant_user_id')
            ->selectRaw('COUNT(*) as total')
            ->where('tenant_id', $tenant->id)
            ->whereNull('ended_at')
            ->groupBy('tenant_user_id')
            ->pluck('total', 'tenant_user_id');

        return $members->map(fn (TenantUser $member) => [
            'id' => $member->id,
            'name' => $member->user?->name ?? 'Usuário sem nome',
            'status' => $member->status->value,
            'role' => $member->role->value,
            'client_count' => (int) ($counts[$member->id] ?? 0),
        ])->values()->all();
    }

    public function clients(Tenant $tenant, string $sourceId): array
    {
        $source = TenantUser::query()->where('tenant_id', $tenant->id)->findOrFail($sourceId);

        return ClientSeller::query()
            ->where('tenant_id', $tenant->id)
            ->where('tenant_user_id', $source->id)
            ->whereNull('ended_at')
            ->with('client:id,company_name,document')
            ->orderBy('client_id')
            ->get()
            ->filter(fn (ClientSeller $assignment) => $assignment->client !== null)
            ->map(fn (ClientSeller $assignment) => [
                'id' => $assignment->client_id,
                'name' => $assignment->client->company_name,
                'document' => $assignment->client->document,
            ])->values()->all();
    }

    public function execute(Tenant $tenant, int $userId, array $data): array
    {
        $this->authorize($tenant, $userId);

        return DB::transaction(function () use ($tenant, $userId, $data): array {
            $source = TenantUser::query()->where('tenant_id', $tenant->id)
                ->lockForUpdate()->findOrFail($data['source_membership_id']);

            $assignments = ClientSeller::query()
                ->where('tenant_id', $tenant->id)
                ->where('tenant_user_id', $source->id)
                ->whereNull('ended_at')
                ->orderBy('client_id')
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'source_membership_id' => 'Este consultor não possui clientes para redistribuir.',
                ]);
            }

            $expectedIds = $assignments->pluck('client_id')->sort()->values()->all();
            $reviewedIds = collect($data['client_ids'])->sort()->values()->all();

            if ($expectedIds !== $reviewedIds) {
                throw ValidationException::withMessages([
                    'client_ids' => 'A carteira mudou desde a conferência. Atualize a lista e revise novamente.',
                ]);
            }

            $destinations = TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', TenantUserStatus::Active->value)
                ->where('id', '!=', $source->id)
                ->whereIn('role', [TenantUserRole::Consultant->value, TenantUserRole::Manager->value])
                ->get()->keyBy('id');

            $selected = collect($data['destination_ids'] ?? [])->unique()->values();
            $mapping = $data['assignments'] ?? [];
            $mode = $data['mode'];

            if ($mode === 'manual') {
                $expected = $assignments->pluck('client_id')->sort()->values()->all();
                $provided = collect(array_keys($mapping))->sort()->values()->all();

                if ($expected !== $provided) {
                    throw ValidationException::withMessages([
                        'assignments' => 'Informe um destino para todos os clientes da carteira atual.',
                    ]);
                }

                $selected = collect(array_values($mapping))->unique()->values();
            }

            if ($selected->isEmpty() || $selected->contains(fn ($id) => ! $destinations->has($id))) {
                throw ValidationException::withMessages([
                    'destination_ids' => 'Selecione somente consultores ativos do mesmo tenant.',
                ]);
            }

            if ($mode === 'single' && $selected->count() !== 1) {
                throw ValidationException::withMessages(['destination_ids' => 'Selecione um único destino.']);
            }

            $loads = ClientSeller::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('ended_at')
                ->whereIn('tenant_user_id', $selected)
                ->select('tenant_user_id')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('tenant_user_id')
                ->pluck('total', 'tenant_user_id');

            $distribution = [];
            foreach ($assignments as $assignment) {
                if ($mode === 'manual') {
                    $destinationId = $mapping[$assignment->client_id];
                } elseif ($mode === 'single') {
                    $destinationId = $selected->first();
                } else {
                    $destinationId = $selected->sort(function ($a, $b) use ($loads) {
                        return ((int) ($loads[$a] ?? 0) <=> (int) ($loads[$b] ?? 0))
                            ?: strcmp($a, $b);
                    })->first();
                }

                if (! $destinations->has($destinationId)) {
                    throw ValidationException::withMessages(['assignments' => 'Destino inválido.']);
                }

                $distribution[$assignment->client_id] = $destinationId;
                $loads[$destinationId] = (int) ($loads[$destinationId] ?? 0) + 1;
            }

            // Revalida todas as carteiras antes de iniciar qualquer alteração.
            $clientIds = $assignments->pluck('client_id');
            $activeCounts = ClientSeller::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('client_id', $clientIds)
                ->whereNull('ended_at')
                ->select('client_id')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('client_id')
                ->pluck('total', 'client_id');

            if ($clientIds->contains(fn ($id) => (int) ($activeCounts[$id] ?? 0) !== 1)) {
                throw ValidationException::withMessages([
                    'source_membership_id' => 'Há clientes com responsáveis inconsistentes. Corrija antes de redistribuir.',
                ]);
            }

            $batchId = (string) Str::uuid();
            $now = now();
            DB::table('client_portfolio_redistributions')->insert([
                'id' => $batchId,
                'tenant_id' => $tenant->id,
                'source_membership_id' => $source->id,
                'actor_user_id' => $userId,
                'mode' => $mode,
                'reason' => $data['reason'],
                'client_count' => $assignments->count(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($assignments as $assignment) {
                $destinationId = $distribution[$assignment->client_id];
                $assignment->update([
                    'ended_at' => $now,
                    'reason' => 'Redistribuição de carteira: ' . $batchId,
                ]);

                ClientSeller::query()->create([
                    'tenant_id' => $tenant->id,
                    'client_id' => $assignment->client_id,
                    'tenant_user_id' => $destinationId,
                    'assigned_by' => $userId,
                    'started_at' => $now,
                    'reason' => 'Redistribuição de carteira: ' . $batchId,
                ]);

                DB::table('client_portfolio_redistribution_items')->insert([
                    'id' => (string) Str::uuid(),
                    'redistribution_id' => $batchId,
                    'client_id' => $assignment->client_id,
                    'destination_membership_id' => $destinationId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['id' => $batchId, 'client_count' => $assignments->count()];
        }, 3);
    }
}
