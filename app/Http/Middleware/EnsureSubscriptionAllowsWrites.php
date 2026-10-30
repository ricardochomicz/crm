<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAllowsWrites
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('active_tenant');

        abort_unless(
            $tenant instanceof Tenant,
            403,
            'Contexto da empresa indisponível.'
        );

        $subscription = $tenant->subscription()->first();

        if (
            $subscription === null
            || ! $subscription->allowsOperationalWrites()
        ) {
            return response()->json([
                'message' => 'A assinatura não permite alterações. Regularize a assinatura para continuar.',
                'code' => 'subscription_write_blocked'
            ], Response::HTTP_FORBIDDEN);
        }
        return $next($request);
    }
}
