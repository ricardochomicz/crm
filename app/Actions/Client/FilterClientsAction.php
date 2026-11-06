<?php
namespace App\Actions\Client;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class FilterClientsAction {
 public function execute(string $tenantId,array $filters):Collection {
  return Client::query()->where('tenant_id',$tenantId)->filter($filters)
   ->with(['operator:id,name','classification:id,code,name','sellers'=>fn($q)=>$q->whereNull('ended_at')->with('membership')])
   ->orderBy('company_name')->orderBy('id')->limit(500)->get();
 }
 public function paginate(string $tenantId,array $filters): LengthAwarePaginator {
  return Client::query()->where('tenant_id',$tenantId)->filter($filters)
   ->with(['operator:id,name','classification:id,code,name','sellers'=>fn($q)=>$q->whereNull('ended_at')->with('membership')])
   ->orderBy('company_name')->orderBy('id')->paginate($filters['per_page']??20);
 }
}
