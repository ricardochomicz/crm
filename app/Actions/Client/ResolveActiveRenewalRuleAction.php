<?php

namespace App\Actions\Client;

use App\Models\ClassificationRenewalRule;
use App\Models\OperatorClassification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ResolveActiveRenewalRuleAction
{
    /**
     * All renewal screens and commands must use the same tenant-owned rule
     * that is effective on the requested date. Historical global rules
     * are retained in the database but are never selected for new renewals.
     */
    public function query(string $tenantId, CarbonImmutable $date): Builder
    {
        return ClassificationRenewalRule::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('classification_id', OperatorClassification::query()
                ->select('id')->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $date->toDateString()))
            ->orderByDesc('valid_from')
            ->orderByDesc('id');
    }

    public function execute(string $tenantId, ?int $classificationId, CarbonImmutable $date): ?ClassificationRenewalRule
    {
        if ($classificationId === null) {
            return null;
        }

        return $this->query($tenantId, $date)
            ->where('classification_id', $classificationId)
            ->first();
    }
}
