<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientTransferRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'client_id', 'from_tenant_user_id', 'to_tenant_user_id',
        'requested_by', 'reviewed_by', 'status', 'reason', 'review_note', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function source()
    {
        return $this->belongsTo(TenantUser::class, 'from_tenant_user_id')->with('user:id,name');
    }

    public function destination()
    {
        return $this->belongsTo(TenantUser::class, 'to_tenant_user_id')->with('user:id,name');
    }
}
