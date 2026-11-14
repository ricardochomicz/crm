<?php

namespace App\Actions\Opportunity;

use App\Models\Opportunity;
use App\Models\OpportunityForecastChange;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Reads the immutable forecast audit trail using the same visibility rules as opportunities. */
class ListOpportunityForecastHistoryAction
{
    public function __construct(private OpportunityAction $opportunities) {}

    public function execute(Tenant $tenant, User $user, string $opportunityId): LengthAwarePaginator
    {
        // This enforces the tenant and current user's opportunity visibility.
        $opportunity = $this->opportunities->find($tenant, $user, $opportunityId);

        return OpportunityForecastChange::query()
            ->where('tenant_id', $tenant->id)
            ->where('opportunity_id', $opportunity->id)
            ->with(['author.user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(static fn (OpportunityForecastChange $change): array => [
                'id' => $change->id,
                'previous_date' => $change->previous_date?->format('Y-m-d'),
                'new_date' => $change->new_date->format('Y-m-d'),
                'previous_stage_id' => $change->previous_stage_id,
                'new_stage_id' => $change->new_stage_id,
                'reason' => $change->reason,
                'created_at' => $change->created_at?->toIso8601String(),
                'author_name' => $change->author?->user?->name ?? 'Usuário',
                'kind' => $change->previous_date === null ? 'initial'
                    : ($change->new_date->gt($change->previous_date) ? 'extension' : 'advance'),
            ]);
    }
}
