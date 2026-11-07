<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientOperatorHistory extends Model
{
    use HasUuids;
    protected $table = 'client_operator_history';
    protected $fillable = ['tenant_id', 'client_id', 'operator_id', 'classification_id', 'changed_at', 'changed_by'];
    protected $casts = ['changed_at' => 'datetime'];
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
    public function classification()
    {
        return $this->belongsTo(OperatorClassification::class, 'classification_id');
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(\Illuminate\Database\Eloquent\Builder $query, array $filters): \Illuminate\Database\Eloquent\Builder
    {
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (isset($filters['operator_id'])) {
            $query->where('operator_id', $filters['operator_id']);
        }
        if (isset($filters['classification_id'])) {
            $query->where('classification_id', $filters['classification_id']);
        }
        return $query;
    }
}
