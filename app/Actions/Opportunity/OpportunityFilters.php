<?php

namespace App\Actions\Opportunity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class OpportunityFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        $status = $filters['status'] ?? 'open';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim($filters['search'] ?? '')) {
            $escaped = addcslashes($search, '%_\\');
            $digits = preg_replace('/\D/', '', $search);
            $query->where(function (Builder $builder) use ($escaped, $digits) {
                $builder->where('title', 'like', "%{$escaped}%")
                    ->orWhere('code', 'like', "%{$escaped}%")
                    ->orWhereHas('client', function (Builder $client) use ($escaped, $digits) {
                        $client->where('company_name', 'like', "%{$escaped}%");
                        if ($digits !== '') {
                            $client->orWhere('document', 'like', "%{$digits}%");
                        }
                    });
            });
        }

        if (! empty($filters['order_type_id'])) {
            $query->where('order_type_id', $filters['order_type_id']);
        }

        if (! empty($filters['funnel_stage_id'])) {
            $query->where('funnel_stage_id', $filters['funnel_stage_id']);
        }

        $today = Carbon::today()->toDateString();
        $first = Carbon::today()->startOfMonth()->toDateString();
        $last = Carbon::today()->endOfMonth()->toDateString();
        $period = $filters['period'] ?? 'current';

        if ($period === 'closed') {
            $query->whereIn('status', ['won', 'lost', 'cancelled']);
            if (! empty($filters['date_from'])) $query->whereDate('closed_at', '>=', $filters['date_from']);
            if (! empty($filters['date_to'])) $query->whereDate('closed_at', '<=', $filters['date_to']);
        } elseif ($period === 'current') {
            $query->where(function (Builder $builder) use ($last) {
                $builder->whereNull('expected_close_at')->orWhereDate('expected_close_at', '<=', $last);
            });
        } elseif ($period === 'overdue') {
            $query->whereDate('expected_close_at', '<', $today);
        } elseif ($period === 'future') {
            $query->whereDate('expected_close_at', '>', $last);
        } elseif ($period === 'custom') {
            if (! empty($filters['date_from'])) {
                $query->whereDate('expected_close_at', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->whereDate('expected_close_at', '<=', $filters['date_to']);
            }
        }

        if (isset($filters['min_amount'])) {
            $query->where('estimated_monthly_amount', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount'])) {
            $query->where('estimated_monthly_amount', '<=', $filters['max_amount']);
        }

        return $query;
    }
}
