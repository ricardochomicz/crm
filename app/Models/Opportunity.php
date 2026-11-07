<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'tenant_user_id', 'client_id', 'funnel_stage_id', 'operator_id',
        'parent_opportunity_id', 'order_type_id', 'code', 'title', 'external_protocol', 'order_type',
        'status', 'description', 'expected_close_at', 'closed_at',
        'estimated_monthly_amount', 'estimated_one_time_amount', 'probability',
        'source', 'lost_reason',
    ];

    protected function casts(): array
    {
        return [
            'expected_close_at' => 'date',
            'closed_at' => 'datetime',
            'estimated_monthly_amount' => 'decimal:2',
            'estimated_one_time_amount' => 'decimal:2',
            'probability' => 'integer',
        ];
    }

    public function orderType(): BelongsTo { return $this->belongsTo(OrderType::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(TenantUser::class, 'tenant_user_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function operator(): BelongsTo { return $this->belongsTo(Operator::class); }
    public function stage(): BelongsTo { return $this->belongsTo(OpportunityFunnelStage::class, 'funnel_stage_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_opportunity_id'); }
    public function items(): HasMany { return $this->hasMany(OpportunityItem::class); }
    public function calendarEvents(): HasMany { return $this->hasMany(CalendarEvent::class); }
}
