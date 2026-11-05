<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommissionTable extends Model {
 protected $guarded = [];
 public function operator() { return $this->belongsTo(Operator::class); }
}
