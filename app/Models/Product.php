<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    public function setNameAttribute($value)
    {
        return $this->attributes['name'] = mb_strtoupper($value);
    }
}
