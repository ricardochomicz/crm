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
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        abort_if($user === null, 401, "Não autenticado.");

        $tenantId = $request->session()->get('active_tenant_id');

        if (! is_string($tenantId) || $tenantId === '') {
            abort(409, 'Selecione um tenant para continuar.');
        }

        $tenant = $user->tenants()
            ->where('tenants.status', TenantStatus::Active->value)
            ->wherePivot('status', TenantUserStatus::Active->value)
            ->whereKey($tenantId)
            ->first();

        if ($tenant === null) {
            $request->session()->forget('active_tenant_id');

            abort(403, 'O tenant selecionado não está disponível.');
        }
        $request->attributes->set('active_tenant', $tenant);
        return $next($request);
    }
}
