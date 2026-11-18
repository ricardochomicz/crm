<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Enums\TenantUserRole;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
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

        // During initial setup, the owner must be able to configure classifications
        // before operational access is enabled. Other writes remain protected.
        $initialClassificationSetup = $tenant->onboarding_completed_at === null
            && $subscription !== null
            && preg_match('~(?:^|/)tenants/[^/]+/operator-classifications(?:/[^/]+(?:/renewal-rules(?:/[^/]+)?)?)?$~', $request->path()) === 1
            && in_array($request->method(), ['POST', 'PATCH'], true)
            && app(ResolveTenantPermissionsAction::class)->membership($request->user(), $tenant)?->role === TenantUserRole::Owner;

        if ($initialClassificationSetup) {
            return $next($request);
        }

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
