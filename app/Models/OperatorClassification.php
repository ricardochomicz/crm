<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class OperatorClassification extends Model
{
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query->when($filters['search'] ?? null, fn(Builder $q, $v) => $q->whereAny(['code', 'name'], 'LIKE', '%' . $v . '%'))->when($filters['operator_id'] ?? null, fn(Builder $q, $v) => $q->where('operator_id', $v))->when(isset($filters['is_active']), fn(Builder $q) => $q->where('is_active', $filters['is_active']));
    }
    protected $fillable = ['operator_id', 'tenant_id', 'code', 'name', 'description', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
    public function rules()
    {
        return $this->hasMany(ClassificationRenewalRule::class, 'classification_id');
    }
}
