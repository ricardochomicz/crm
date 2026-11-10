<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientOperatorClassification extends Model
{
    protected $fillable = ['tenant_id', 'client_id', 'operator_id', 'classification_id'];

    public function operator() { return $this->belongsTo(Operator::class); }
    public function classification() { return $this->belongsTo(OperatorClassification::class); }
}
