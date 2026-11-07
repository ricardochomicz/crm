<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpportunityFunnelStage extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'name', 'position', 'is_active'];

    protected function casts(): array { return ['position' => 'integer', 'is_active' => 'boolean']; }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class, 'funnel_stage_id'); }
}
