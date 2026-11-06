<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\FilterUserTenantsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantsRequest;
use App\Models\Tenant;

class ListTenantsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ListTenantsRequest $request, FilterUserTenantsAction $action): array
    {
        return $action->execute($request->user(), $request->validated())
            ->through(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->type->value,
                'role' => $tenant->membership->role->value,
            ])
            ->toArray();
    }
}
