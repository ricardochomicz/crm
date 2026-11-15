<?php

namespace App\Actions\Commission;

use App\Models\CommissionDeflatorTier;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ResolveCommissionDeflatorTierAction
{
    /**
     * Resolve the applicable reduction tier without assuming any operator percentages.
     * A null rate is never interpreted as zero: it means the rule is unresolved.
     *
     * @return array{reduction_percentage: string, deflator_percentage: ?string, tier_id: ?string, issue: ?string}
     */
    public function execute(
        string $tenantId,
        int $operatorId,
        string $orderType,
        string $referenceDate,
        string $previousMonthlyAmount,
        string $newMonthlyAmount,
    ): array {
        $previous = BigDecimal::of($previousMonthlyAmount);
        $current = BigDecimal::of($newMonthlyAmount);
        if ($previous->isLessThanOrEqualTo(0) || $current->isNegative()) {
            return [
                'reduction_percentage' => '0.0000', 'deflator_percentage' => null,
                'tier_id' => null, 'issue' => 'invalid_monthly_amount',
            ];
        }

        $reduction = $previous->minus($current);
        if ($reduction->isLessThanOrEqualTo(0)) {
            return [
                'reduction_percentage' => '0.0000', 'deflator_percentage' => '0.0000',
                'tier_id' => null, 'issue' => null,
            ];
        }

        $percentage = $reduction->multipliedBy(100)->dividedBy($previous, 4, RoundingMode::HalfUp);
        $matches = CommissionDeflatorTier::query()
            ->where('tenant_id', $tenantId)
            ->where('operator_id', $operatorId)
            ->where('order_type', $orderType)
            ->where('is_active', true)
            ->where('starts_on', '<=', $referenceDate)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $referenceDate))
            ->where('reduction_from_percentage', '<=', (string) $percentage)
            ->where(fn ($query) => $query->whereNull('reduction_to_percentage')
                ->orWhere('reduction_to_percentage', '>', (string) $percentage))
            ->limit(2)->get();

        if ($matches->count() !== 1) {
            return [
                'reduction_percentage' => (string) $percentage,
                'deflator_percentage' => null,
                'tier_id' => null,
                'issue' => $matches->isEmpty() ? 'deflator_tier_missing' : 'deflator_tier_ambiguous',
            ];
        }

        $tier = $matches->first();

        return [
            'reduction_percentage' => (string) $percentage,
            'deflator_percentage' => (string) $tier->commission_deflator_percentage,
            'tier_id' => $tier->id,
            'issue' => null,
        ];
    }
}
