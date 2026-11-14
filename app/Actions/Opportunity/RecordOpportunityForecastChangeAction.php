<?php

namespace App\Actions\Opportunity;

use App\Models\Comment;
use App\Models\Opportunity;
use App\Models\OpportunityForecastChange;
use App\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Records a forecast revision as structured audit data and a readable timeline entry.
 * Caller must execute inside the same transaction as the opportunity update.
 */
class RecordOpportunityForecastChangeAction
{
    public function execute(
        Opportunity $opportunity,
        TenantUser $actor,
        ?string $previousDate,
        string $newDate,
        ?string $previousStageId,
        ?string $newStageId,
        ?string $reason,
        string $context
    ): void {
        if ($previousDate === $newDate) {
            return;
        }

        $reason = trim($reason ?? '');
        if ($previousDate !== null && $newDate > $previousDate && mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'forecast_reason' => 'Informe uma justificativa de pelo menos 10 caracteres para prorrogar a previsão.',
            ]);
        }

        OpportunityForecastChange::create([
            'tenant_id' => $opportunity->tenant_id,
            'opportunity_id' => $opportunity->id,
            'tenant_user_id' => $actor->id,
            'previous_date' => $previousDate,
            'new_date' => $newDate,
            'previous_stage_id' => $previousStageId,
            'new_stage_id' => $newStageId,
            'reason' => $reason ?: null,
        ]);

        $format = static fn (?string $date): string => $date
            ? \Carbon\Carbon::parse($date)->format('d/m/Y')
            : 'Não informada';

        Comment::create([
            'tenant_id' => $opportunity->tenant_id,
            'tenant_user_id' => $actor->id,
            'commentable_type' => 'opportunity',
            'commentable_id' => $opportunity->id,
            'content' => sprintf(
                '%s Previsão de fechamento atualizada de %s para %s.%s',
                $context,
                $format($previousDate),
                $format($newDate),
                $reason !== '' ? ' Justificativa: '.$reason : ''
            ),
        ]);
    }
}
