<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    protected $fillable = ['tenant_id', 'user_id', 'category', 'subject', 'description', 'status'];
}
