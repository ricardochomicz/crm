<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'tenant_user_id', 'created_by_tenant_user_id', 'client_id',
        'title', 'description', 'type', 'status', 'priority', 'all_day',
        'starts_at', 'ends_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'all_day' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_tenant_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
