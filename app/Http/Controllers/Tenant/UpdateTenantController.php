<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\Response;

class UpdateTenantController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateTenantRequest $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $tenant->update(
            $request->safe()->only([
                'name',
                'trade_name',
                'email',
                'phone',
                'timezone'
            ])
        );

        return response()->noContent();
    }
}
