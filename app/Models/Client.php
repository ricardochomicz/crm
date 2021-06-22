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

    public function setContactNameAttribute($value)
    {
        return $this->attributes['contact_name'] = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public function setContactEmailAttribute($value)
    {
        return $this->attributes['contact_email'] = mb_strtolower($value);
    }
    
    public function setContactAdmAttribute($value)
    {
         return $this->attributes['contact_adm'] = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }


}
