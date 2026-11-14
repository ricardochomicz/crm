<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityForecastChange extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'opportunity_id', 'tenant_user_id', 'previous_date', 'new_date',
        'previous_stage_id', 'new_stage_id', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_date' => 'date',
            'new_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }
}
