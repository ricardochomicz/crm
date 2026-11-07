<?php

namespace App\Http\Controllers\Tenant\Subscriptions;

use App\Actions\Tenant\ShowTenantSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ShowTenantSubscriptionRequest;
use App\Http\Resources\Tenant\SubscriptionResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class ShowTenantSubscriptionController extends Controller
{
    public function __invoke(
        ShowTenantSubscriptionRequest $request,
        ShowTenantSubscriptionAction $action
    ): JsonResponse {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $result = $action->execute($tenant);

        if ($result['subscription'] === null) {
            return response()->json([
                'message' => 'A empresa não possui assinatura.',
                'code' => 'subscription_not_found',
            ], 404);
        }

        return (new SubscriptionResource($result['subscription'], [
            'usersCount' => $result['usersCount'],
            'reservedSeatsCount' => $result['reservedSeatsCount'],
        ]))->response();
    }
}
