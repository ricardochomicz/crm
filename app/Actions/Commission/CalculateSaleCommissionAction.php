<?php

namespace App\Actions\Commission;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class CalculateSaleCommissionAction
{
    /**
     * @param  list<array{role: string, tenant_user_id: ?string, commercial_participant_id: ?string, name: string, percentage: string, rule_id: string, rule_source: string}>  $participants
     * @return array{base_amount: string, gross_amount: string, deduction_amount: string, net_amount: string, participant_total: string, company_balance: string, participant_amounts: list<array<string, mixed>>, issues: list<string>}
     */
    public function execute(string $baseAmount, string $companyPercentage, string $deductionPercentage, array $participants): array
    {
        $base = BigDecimal::of($baseAmount)->toScale(2, RoundingMode::HalfUp);
        $gross = $this->percentage($base, $companyPercentage);
        $deduction = $this->percentage($gross, $deductionPercentage);
        $net = $gross->minus($deduction);
        $participantTotal = BigDecimal::of('0.00');
        $amounts = [];

        foreach ($participants as $participant) {
            $amount = $this->percentage($base, $participant['percentage']);
            $participantTotal = $participantTotal->plus($amount);
            $amounts[] = [...$participant, 'amount' => (string) $amount];
        }

        $balance = $net->minus($participantTotal);

        return [
            'base_amount' => (string) $base,
            'gross_amount' => (string) $gross,
            'deduction_amount' => (string) $deduction,
            'net_amount' => (string) $net,
            'participant_total' => (string) $participantTotal,
            'company_balance' => (string) $balance,
            'participant_amounts' => $amounts,
            'issues' => $balance->isNegative() ? ['participant_total_exceeds_net_commission'] : [],
        ];
    }

    private function percentage(BigDecimal $base, string $percentage): BigDecimal
    {
        return $base->multipliedBy($percentage)->dividedBy(100, 2, RoundingMode::HalfUp);
    }
}
