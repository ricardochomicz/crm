<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeTenantUnitAssignmentsAction
{
    /** @param array{kind: string, operation: string, ids: list<string>} $data */
    public function handle(User $actor, Tenant $tenant, string $unitId, array $data): void
    {
        DB::transaction(function () use ($actor, $tenant, $unitId, $data): void {
            $tenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageUnits', $tenant);
            abort_unless($tenant->subscription()->lockForUpdate()->first()?->allowsOperationalWrites(), 403, 'A assinatura não permite alterar vínculos.');
            $unit = $tenant->units()->findOrFail($unitId);
            if ($data['kind'] === 'members') {
                if ($data['operation'] === 'add') {
                    $unit->members()->syncWithoutDetaching(array_fill_keys($data['ids'], ['tenant_id' => $tenant->id]));
                } else {
                    $blocked = TenantUser::query()->where('tenant_id', $tenant->id)->whereIn('id', $data['ids'])
                        ->where(fn (Builder $members): Builder => $members
                            ->whereHas('team', fn (Builder $teams): Builder => $teams->where('tenant_unit_id', $unitId))
                            ->orWhereHas('supervisedTeams', fn (Builder $teams): Builder => $teams->where('tenant_unit_id', $unitId)))->exists();
                    if ($blocked) {
                        throw ValidationException::withMessages(['ids' => 'Atualize a equipe deste membro antes de remover seu vínculo com a unidade.']);
                    }
                    $unit->members()->detach($data['ids']);
                }

                return;
            }

            $destination = $data['operation'] === 'add' ? $unit : $tenant->units()->where('is_headquarters', true)->firstOrFail();
            if ($data['operation'] === 'remove' && $unit->is_headquarters) {
                throw ValidationException::withMessages(['ids' => 'Transfira a equipe para uma filial para retirá-la da matriz.']);
            }
            $query = $tenant->teams()->whereIn('id', $data['ids']);
            if ($data['operation'] === 'remove') {
                $query->where('tenant_unit_id', $unitId);
            }
            $teams = $query->lockForUpdate()->get();
            $tenant->teams()->whereIn('id', $teams->modelKeys())->update(['tenant_unit_id' => $destination->id]);
            $members = TenantUser::query()->where('tenant_id', $tenant->id)->whereIn('team_id', $teams->modelKeys())->pluck('id')
                ->merge($teams->pluck('supervisor_membership_id')->filter())->unique();
            $destination->members()->syncWithoutDetaching($members->mapWithKeys(fn (string $id): array => [$id => ['tenant_id' => $tenant->id]])->all());
        }, attempts: 5);
    }
}
