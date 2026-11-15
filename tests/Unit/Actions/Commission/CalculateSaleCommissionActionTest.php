<?php

namespace Tests\Unit\Actions\Commission;

use App\Actions\Commission\CalculateSaleCommissionAction;
use PHPUnit\Framework\TestCase;

class CalculateSaleCommissionActionTest extends TestCase
{
    public function test_claro_example_uses_half_up_rounding_per_line(): void
    {
        $calculation = (new CalculateSaleCommissionAction)->execute('49.90', '500', '9', [
            [
                'role' => 'CONSULTANT',
                'tenant_user_id' => 'consultant-id',
                'commercial_participant_id' => null,
                'name' => 'Consultor',
                'percentage' => '300',
                'rule_id' => 'rule-id',
                'rule_source' => 'team_rule',
            ],
        ]);

        self::assertSame('249.50', $calculation['gross_amount']);
        self::assertSame('22.46', $calculation['deduction_amount']);
        self::assertSame('227.04', $calculation['net_amount']);
        self::assertSame('149.70', $calculation['participant_total']);
        self::assertSame('77.34', $calculation['company_balance']);
        self::assertSame([], $calculation['issues']);
    }

    public function test_blocks_participant_payout_exceeding_net_company_commission(): void
    {
        $calculation = (new CalculateSaleCommissionAction)->execute('50.00', '100', '9', [
            [
                'role' => 'CONSULTANT',
                'tenant_user_id' => 'consultant-id',
                'commercial_participant_id' => null,
                'name' => 'Consultor',
                'percentage' => '100',
                'rule_id' => 'rule-id',
                'rule_source' => 'team_rule',
            ],
        ]);

        self::assertSame('-4.50', $calculation['company_balance']);
        self::assertSame(['participant_total_exceeds_net_commission'], $calculation['issues']);
    }
}
