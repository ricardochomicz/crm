<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Client extends Model
{
    use HasUuids, \Illuminate\Database\Eloquent\SoftDeletes;
    protected $fillable = ['tenant_id', 'person_type', 'document', 'company_name', 'phone', 'email', 'zip_code', 'address', 'address_number', 'address_complement', 'neighborhood', 'city', 'state', 'operator_id', 'classification_id', 'contract_started_on', 'contract_ends_on', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'contract_started_on' => 'date:Y-m-d', 'contract_ends_on' => 'date:Y-m-d'];
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when($filters['search'] ?? null, fn(Builder $q, $search) => $q->whereAny(['company_name', 'document', 'email'], 'LIKE', '%' . $search . '%'))
            ->when($filters['operator_id'] ?? null, fn(Builder $q, $id) => $q->where('operator_id', $id))
            ->when(isset($filters['is_active']), fn(Builder $q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['classification_id'] ?? null, fn(Builder $q, $id) => $q->where('classification_id', $id));
    }
    public function contracts()
    {
        return $this->hasMany(ClientContract::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
    public function classification()
    {
        return $this->belongsTo(OperatorClassification::class, 'classification_id');
    }
    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }
    public function sellers()
    {
        return $this->hasMany(ClientSeller::class);
    }
    public function operatorHistory()
    {
        return $this->hasMany(ClientOperatorHistory::class);
    }
}
