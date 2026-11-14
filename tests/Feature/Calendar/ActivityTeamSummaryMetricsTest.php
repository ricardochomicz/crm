<?php

namespace Tests\Feature\Calendar;

use App\Actions\Calendar\CalendarEventAction;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\CommercialOrder;
use App\Models\Operator;
use App\Models\Product;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityTeamSummaryMetricsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function membership(Tenant $tenant, string $name): TenantUser
    {
        return TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => User::factory()->create(['name' => $name])->id,
            'role' => 'CONSULTANT',
            'status' => 'ACTIVE',
        ]);
    }

    private function opportunity(Tenant $tenant, TenantUser $member, string $status): Opportunity
    {
        $client = Client::query()->create([
            'tenant_id' => $tenant->id,
            'person_type' => 'PF',
            'document' => (string) random_int(10000000000, 99999999999),
            'company_name' => 'Cliente teste',
        ]);

        return Opportunity::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $member->id,
            'client_id' => $client->id,
            'code' => 'TEST-' . Str::random(12),
            'title' => 'Oportunidade teste',
            'order_type' => 'NEW',
            'status' => $status,
        ]);
    }

    private function activity(Tenant $tenant, TenantUser $member, ?Opportunity $opportunity, string $status, string $completedAt): void
    {
        CalendarEvent::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $member->id,
            'created_by_tenant_user_id' => $member->id,
            'opportunity_id' => $opportunity?->id,
            'title' => 'Acompanhamento',
            'type' => 'task',
            'status' => $status,
            'priority' => 'normal',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'completed_at' => $status === 'completed' ? $completedAt : null,
        ]);
    }

    public function test_counts_distinct_opportunities_per_consultant_and_completion_period(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0));

        $tenant = Tenant::factory()->create();
        $first = $this->membership($tenant, 'Ana');
        $second = $this->membership($tenant, 'Bruno');
        $third = $this->membership($tenant, 'Carla');
        $won = $this->opportunity($tenant, $first, 'won');
        $open = $this->opportunity($tenant, $first, 'open');
        $other = $this->opportunity($tenant, $second, 'won');
        // The schema permits one order per opportunity; multiple completed
        // activities linked to that opportunity must still yield one result.
        $operator = Operator::factory()->create();
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'operator_id' => $operator->id,
        ]);
        CommercialOrder::query()->create([
            'tenant_id' => $tenant->id,
            'opportunity_id' => $won->id,
            'operator_id' => $operator->id,
            'product_id' => $product->id,
            'order_type' => 'NEW',
            'customer_name' => 'Cliente teste',
            'monthly_amount' => 100,
            'line_quantity' => 1,
            'status' => 'DRAFT',
            'sold_on' => '2026-11-12',
        ]);

        // Two completions for one opportunity must not double-count its outcome.
        $this->activity($tenant, $first, $won, 'completed', '2026-11-10 10:00:00');
        $this->activity($tenant, $first, $won, 'completed', '2026-11-11 10:00:00');
        $this->activity($tenant, $first, $open, 'completed', '2026-11-12 10:00:00');
        $this->activity($tenant, $first, null, 'completed', '2026-11-12 11:00:00');
        $this->activity($tenant, $first, $won, 'completed', '2026-10-01 10:00:00');
        $this->activity($tenant, $second, $other, 'completed', '2026-11-13 10:00:00');
        $this->activity($tenant, $second, $other, 'pending', '2026-11-13 10:00:00');
        // An activity for a different tenant must never appear in this team's report.
        $foreignTenant = Tenant::factory()->create();
        $foreignMember = $this->membership($foreignTenant, 'Consultor externo');
        $foreignOpportunity = $this->opportunity($foreignTenant, $foreignMember, 'won');
        $this->activity($foreignTenant, $foreignMember, $foreignOpportunity, 'completed', '2026-11-12 10:00:00');

        // Deleted opportunities are excluded from won outcomes, but still count
        // as completed activities if their event link is retained.
        $deleted = $this->opportunity($tenant, $second, 'won');
        $this->activity($tenant, $second, $deleted, 'completed', '2026-11-13 11:00:00');
        $deleted->delete();

        $actor = User::factory()->create();
        Gate::before(fn (User $user, string $ability) => $user->is($actor) && $ability === 'viewTeamCalendar' ? true : null);

        // Keep authorization membership selection controlled; exercise the real SQL aggregation.
        $action = $this->partialMock(CalendarEventAction::class, function ($mock) use ($first, $second, $third) {
            $mock->shouldReceive('assignableMembers')->once()->andReturn([
                ['id' => $first->id, 'name' => 'Ana'],
                ['id' => $second->id, 'name' => 'Bruno'],
                ['id' => $third->id, 'name' => 'Carla'],
            ]);
        });

        $rows = collect($action->activitiesTeamSummary($tenant, $actor, [
            'from' => '2026-11-01',
            'to' => '2026-11-14',
        ]))->keyBy('name');

        $this->assertSame(4, $rows['Ana']['completed_period']);
        $this->assertSame(2, $rows['Ana']['followed_opportunities_period']);
        $this->assertSame(1, $rows['Ana']['won_opportunities_period']);
        $this->assertSame(1, $rows['Ana']['opportunities_with_orders_period']);
        $this->assertSame(2, $rows['Bruno']['completed_period']);
        // The soft-deleted opportunity is still linked to its historical activity.
        $this->assertSame(2, $rows['Bruno']['followed_opportunities_period']);
        $this->assertSame(1, $rows['Bruno']['won_opportunities_period']);
        $this->assertSame(0, $rows['Carla']['completed_period']);
        $this->assertSame(0, $rows['Carla']['followed_opportunities_period']);
    }
}
