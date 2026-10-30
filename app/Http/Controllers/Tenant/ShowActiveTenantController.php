<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class ShowActiveTenantController extends Controller
{
    /**
     * @return array{
     *     data: array{
     *         id: string,
     *         name: string,
     *         type: string,
     *         role: string
     *     }
     * }
     */
    public function __invoke(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        return [
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'type' => $tenant->type->value,
                'role' => $tenant->membership->role->value
            ]
        ];
    }
}
