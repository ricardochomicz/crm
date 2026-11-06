<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class CommissionTable extends Model {
 use HasUuids;
 protected $fillable = ['tenant_id','operator_id','name','order_type','percentage','deduction_percentage','is_active','description'];
 protected function casts(): array { return ['percentage'=>'decimal:2','deduction_percentage'=>'decimal:2','is_active'=>'boolean']; }
 public function operator() { return $this->belongsTo(Operator::class); }
}
