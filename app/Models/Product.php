<?php

namespace App\Models;

class Product extends \Illuminate\Database\Eloquent\Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
    protected $guarded = ['id'];
}
