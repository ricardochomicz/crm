<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'client', 'type', 'forecast', 'funnel', 'status', 'recipe', 'lines', 'active', 'archive', 'activate',
        'renew', 'activity_status', 'activity_date', 'order_status', 'offer', 'renew_date'
    ];

    public function setForecastAttribute($value)
    {
        if ($value)
            return $this->attributes['forecast'] = implode("-", array_reverse(explode("/", $value)));
    }

    public function setActivateAttribute($value)
    {
        $this->attributes['activate'] = empty($value) ? null : implode("-", array_reverse(explode("/", $value)));
    }

//    public function setRenewDateAttribute($value)
//    {
//        $this->attributes['renew_date'] = empty($value) ? null : implode("-", array_reverse(explode("/", $value)));
//    }

    public function setActivityDateAttribute($value)
    {
        if ($value)
            return $this->attributes['activity_date'] = implode("-", array_reverse(explode("/", $value)));
    }

    public function client()
    {
        return $this->hasMany(Client::class, 'id', 'client');
    }

    public function terms()
    {
        return $this->hasMany(OpportunityTerm::class);
    }
}
