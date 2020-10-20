<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = ['id'];

    public function setContactAttribute($value)
    {
        return $this->attributes['contact'] = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public function setEmailAttribute($value)
    {
        return $this->attributes['email'] = mb_strtolower($value);
    }

    public function setAdmEmail($value)
    {
        return $this->attributes['adm'] = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
