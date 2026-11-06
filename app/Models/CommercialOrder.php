<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CommercialOrder extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'operator_id', 'product_id', 'order_type', 'customer_name', 'customer_document', 'monthly_amount', 'line_quantity', 'status', 'sold_on', 'notes'];
    protected $casts = ['monthly_amount' => 'decimal:2', 'line_quantity' => 'integer', 'sold_on' => 'date:Y-m-d'];
    public function operator() { return $this->belongsTo(Operator::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function participants() { return $this->hasMany(CommercialOrderParticipant::class); }
}
