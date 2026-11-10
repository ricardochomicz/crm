<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OpportunityProposal extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'opportunity_id', 'version', 'snapshot',
        'public_token', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
}
