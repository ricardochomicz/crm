<?php

namespace App\Actions\Client;

use App\Models\OperatorClassification;
use Illuminate\Database\Eloquent\Collection;

class FilterOperatorClassificationsAction
{
  public function execute(?string $tenantId, array $filters = []): Collection
  {
    return OperatorClassification::query()->filter($filters)
      ->where('tenant_id', $tenantId)
      ->with(['operator:id,name', 'rules' => fn($q) => $q->where('tenant_id', $tenantId)->orderByDesc('valid_from')])
      ->orderBy('operator_id')->orderBy('sort_order')->orderBy('code')->get();
  }
}
