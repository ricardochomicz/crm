<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = ['id'];

    public function setDocumentAttribute($value)
    {
        $chars = array(".", "/", "-");
        return $this->attributes['document'] = str_replace($chars, '', $value);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'client');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'client');
    }
}
