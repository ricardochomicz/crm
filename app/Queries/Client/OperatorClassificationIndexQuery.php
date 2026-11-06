<?php
namespace App\Queries\Client;
use App\Models\OperatorClassification;
use Illuminate\Database\Eloquent\Collection;
class OperatorClassificationIndexQuery {
 public function get(?string $tenantId,array $filters=[]):Collection {
  return OperatorClassification::query()->filter($filters)
   ->where(fn($q)=>$q->whereNull('tenant_id')->when($tenantId!==null,fn($q)=>$q->orWhere('tenant_id',$tenantId)))
   ->with(['operator:id,name','rules'=>fn($q)=>$q->where(fn($x)=>$x->whereNull('tenant_id')->when($tenantId!==null,fn($x)=>$x->orWhere('tenant_id',$tenantId)))->orderByDesc('valid_from')])
   ->orderBy('operator_id')->orderBy('sort_order')->orderBy('code')->get();
 }
}
