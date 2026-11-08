<?php

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\Comment;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use Carbon\CarbonImmutable;

class EvaluateClientTransferAction
{
    public function execute(Client $client): array
    {
        $tenantId = $client->tenant_id;
        $opportunities = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->where('client_id', $client->id);

        $opportunityIds = (clone $opportunities)->select('id');
        $orders = CommercialOrder::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($client, $opportunityIds): void {
                $query->where('client_id', $client->id)
                    ->orWhereIn('opportunity_id', $opportunityIds);
                if ($client->document) {
                    $query->orWhere(function ($legacy) use ($client): void {
                        $legacy->whereNull('client_id')
                            ->where('customer_document', $client->document);
                    });
                }
            });

        $openOpportunities = (clone $opportunities)->whereNotIn('status', ['won', 'lost', 'cancelled'])->exists();
        $openOrders = (clone $orders)->whereNull('administratively_completed_at')->exists();

        $latestInteraction = Comment::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($client, $opportunities, $orders): void {
                $query->where(function ($direct) use ($client): void {
                    $direct->where('commentable_type', 'client')->where('commentable_id', $client->id);
                })->orWhere(function ($related) use ($opportunities): void {
                    $related->where('commentable_type', 'opportunity')
                        ->whereIn('commentable_id', (clone $opportunities)->select('id'));
                })->orWhere(function ($related) use ($orders): void {
                    $related->where('commentable_type', 'commercial_order')
                        ->whereIn('commentable_id', (clone $orders)->select('id'));
                });
            })
            ->max('created_at');

        // Sem interação registrada, usamos a data de criação do cadastro como marco inicial.
        $lastActivity = CarbonImmutable::parse($latestInteraction ?? $client->created_at);
        $inactive = $lastActivity->lt(CarbonImmutable::now()->subDays(90));

        return [
            'eligible' => $inactive,
            'requires_special_review' => $openOpportunities || $openOrders,
            'last_interaction_at' => $latestInteraction,
            'inactive_for_90_days' => $inactive,
            'has_open_opportunities' => $openOpportunities,
            'has_open_orders' => $openOrders,
        ];
    }
}
