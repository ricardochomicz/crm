<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'document', 'trade_name', 'email', 'phone', 'status', 'timezone'])]
class Tenant extends Model
{
    use HasUuids;
}
