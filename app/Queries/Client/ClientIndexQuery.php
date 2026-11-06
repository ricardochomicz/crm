<?php
namespace App\Queries\Client;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
class ClientIndexQuery {
 public function get(string $tenantId,array $filters):Collection {
  return Client::query()->where('tenant_id',$tenantId)->filter($filters)
   ->with(['operator:id,name','classification:id,code,name','sellers'=>fn($q)=>$q->whereNull('ended_at')->with('membership')])
   ->orderBy('company_name')->limit(500)->get();
 }
 public function paginate(string $tenantId,array $filters) {
  return Client::query()->where('tenant_id',$tenantId)->filter($filters)
   ->with(['operator:id,name','classification:id,code,name','sellers'=>fn($q)=>$q->whereNull('ended_at')->with('membership')])
   ->orderBy('company_name')->paginate($filters['per_page']??20);
 }
}
