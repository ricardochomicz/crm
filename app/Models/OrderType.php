<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OrderType extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'requires_number', 'is_active', 'position'];

    protected function casts(): array
    {
        return ['requires_number' => 'boolean', 'is_active' => 'boolean', 'position' => 'integer'];
    }
}
