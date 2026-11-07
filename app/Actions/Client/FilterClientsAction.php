<?php

namespace App\Actions\Client;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FilterClientsAction
{
    public function __construct(private ScopeVisibleClientsAction $visibility) {}

    private function query(string $tenantId, int $userId, array $filters)
    {
        return $this->visibility->execute(Client::query(), $tenantId, $userId)
            ->filter($filters)
            ->with([
                'operator:id,name',
                'classification:id,code,name',
                'sellers' => fn ($query) => $query->whereNull('ended_at')->with('membership'),
            ])
            ->orderBy('company_name')->orderBy('id');
    }

    public function execute(string $tenantId, array $filters, int $userId): Collection
    {
        return $this->query($tenantId, $userId, $filters)->limit(500)->get();
    }

    public function paginate(string $tenantId, array $filters, int $userId): LengthAwarePaginator
    {
        return $this->query($tenantId, $userId, $filters)->paginate($filters['per_page'] ?? 20);
    }
}
