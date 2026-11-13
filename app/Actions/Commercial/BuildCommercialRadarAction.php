<?php

namespace App\Actions\Commercial;

use App\Models\CalendarEvent;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

class BuildCommercialRadarAction
{
    public function __construct(
        private readonly ResolveCommercialRadarMembersAction $members,
    ) {}

    /**
     * Return only records within the authenticated user's authorized membership scope.
     *
     * @return array{items: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function execute(Tenant $tenant, User $user, ?string $memberId = null): array
    {
        $memberIds = $this->members->execute($tenant, $user, $memberId);

        $now = now();
        $items = [];

        if ($user->can('viewOpportunities', $tenant)) {
            $opportunities = Opportunity::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('tenant_user_id', $memberIds)
                ->where('status', 'open')
                ->whereDate('expected_close_at', '<=', $now->copy()->addDays(2)->toDateString())
                ->orderBy('expected_close_at')
                ->limit(50)
                ->get(['id', 'title', 'expected_close_at']);

            foreach ($opportunities as $opportunity) {
                $date = $opportunity->expected_close_at?->toDateString();
                $overdue = $date !== null && $date < $now->toDateString();
                $items[] = [
                    'id' => $opportunity->id,
                    'type' => 'opportunity',
                    'title' => $opportunity->title,
                    'reason' => $overdue ? 'Fechamento previsto em atraso' : 'Fechamento próximo',
                    'priority' => $overdue ? 'urgent' : 'high',
                    'due_at' => $date,
                    'destination_id' => $opportunity->id,
                ];
            }
        }

        if ($user->can('viewCalendar', $tenant)) {
            $activities = CalendarEvent::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('tenant_user_id', $memberIds)
                ->whereIn('type', ['task', 'reminder'])
                ->where('status', 'pending')
                ->where('ends_at', '<=', $now->copy()->addDay())
                ->orderBy('ends_at')
                ->limit(50)
                ->get(['id', 'title', 'ends_at', 'opportunity_id']);

            foreach ($activities as $activity) {
                $overdue = $activity->ends_at->lessThan($now);
                $items[] = [
                    'id' => $activity->id,
                    'type' => 'activity',
                    'title' => $activity->title,
                    'reason' => $overdue ? 'Atividade vencida' : 'Atividade com prazo próximo',
                    'priority' => $overdue ? 'urgent' : 'high',
                    'due_at' => $activity->ends_at->toIso8601String(),
                    'destination_id' => $activity->id,
                ];
            }
        }

        if ($user->can('viewOrders', $tenant)) {
            $orders = CommercialOrder::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('tenant_user_id', $memberIds)
                ->whereNull('administratively_completed_at')
                ->whereNull('activated_at')
                ->whereNotNull('expected_activation_at')
                ->whereDate('expected_activation_at', '<', $now->toDateString())
                ->orderBy('expected_activation_at')
                ->limit(50)
                ->get(['id', 'code', 'customer_name', 'expected_activation_at']);

            foreach ($orders as $order) {
                $items[] = [
                    'id' => $order->id,
                    'type' => 'order',
                    'title' => $order->code ?: $order->customer_name,
                    'reason' => 'Ativação prevista em atraso',
                    'priority' => 'urgent',
                    'due_at' => Carbon::parse($order->expected_activation_at)->toDateString(),
                    'destination_id' => $order->id,
                ];
            }
        }

        usort($items, static function (array $a, array $b): int {
            $priority = ['urgent' => 0, 'high' => 1];

            return ($priority[$a['priority']] <=> $priority[$b['priority']])
                ?: strcmp((string) $a['due_at'], (string) $b['due_at']);
        });

        $total = count($items);
        $urgent = count(array_filter($items, static fn (array $item): bool => $item['priority'] === 'urgent'));
        $high = $total - $urgent;
        $items = array_slice($items, 0, 30);

        return [
            'items' => $items,
            'summary' => [
                'total' => $total,
                'urgent' => $urgent,
                'high' => $high,
            ],
        ];
    }
}
