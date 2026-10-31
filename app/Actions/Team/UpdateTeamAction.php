<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateTeamAction
{
    public function handle(
        User $owner,
        Tenant $tenant,
        string $teamId,
        string $name,
    ): void {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages([
                'name' => 'Informe um nome com até 100 caracteres.',
            ]);
        }

        DB::transaction(function () use (
            $owner,
            $tenant,
            $teamId,
            $name,
        ): void {
            $tenant = Tenant::query()
                ->lockForUpdate()
                ->findOrFail($tenant->getKey());

            Gate::forUser($owner)->authorize('updateTeam', $tenant);

            $subscription = $tenant->subscription()
                ->lockForUpdate()
                ->first();

            if (
                $subscription === null
                || ! $subscription->allowsOperationalWrites()
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'A assinatura não permite editar equipes.',
                ]);
            }

            $team = $tenant->teams()
                ->whereKey($teamId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($team->name === $name) {
                return;
            }

            $team->name = $name;
            $team->save();
        }, attempts: 5);
    }
}
