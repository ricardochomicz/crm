<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientContract extends Model
{
    use HasUuids;

    public const POLICY_WEIGHTED_AVERAGE = 'WEIGHTED_AVERAGE';
    public const POLICY_ORIGINAL_DATE = 'ORIGINAL_DATE';
    public const POLICY_FIXED_DATE = 'FIXED_DATE';
    public const POLICY_PENDING = 'PENDING';

    protected $fillable = [
        'tenant_id', 'client_id', 'operator_id', 'classification_id',
        'renewal_policy', 'fixed_renewal_date', 'status',
    ];

    protected $casts = ['fixed_renewal_date' => 'date:Y-m-d'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(OperatorClassification::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CommercialOrder::class);
    }
}
