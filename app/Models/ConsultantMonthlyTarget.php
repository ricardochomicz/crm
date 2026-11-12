<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConsultantMonthlyTarget extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'tenant_user_id', 'month', 'amount'];

    protected $casts = ['month' => 'date:Y-m-d', 'amount' => 'decimal:2'];
}
