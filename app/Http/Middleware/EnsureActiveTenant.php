<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Enums\TenantUserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $source = 'route'): Response
    {
        $user = $request->user();

        abort_if($user === null, 401, "Não autenticado.");

        $tenantId = $source === 'selected'
            ? $request->session()->get('active_tenant_id')
            : $request->route('tenantId');

        if (! is_string($tenantId) || $tenantId === '') {
            abort(
                $source === 'selected' ? 409 : 404,
                $source === 'selected'
                    ? 'Selecione um tenant para continuar.'
                    : 'Tenant não disponível.'
            );
        }

        $tenant = $user->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->whereKey($tenantId)
            ->first();

        if ($tenant === null) {
            if ($source === 'selected') {
                $request->session()->forget('active_tenant_id');
            }


            abort(404, 'Tenant não disponível.');
        }
        $request->attributes->set('active_tenant', $tenant);
        return $next($request);
    }
}
