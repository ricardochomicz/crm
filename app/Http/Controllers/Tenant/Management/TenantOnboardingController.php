<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Actions\Tenant\GetTenantOnboardingProgressAction;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TenantOnboardingController extends Controller
{
    public function show(Request $request, string $tenantId, GetTenantOnboardingProgressAction $progress): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');

        return response()->json(['data' => $progress->execute($tenant)]);
    }

    public function complete(Request $request, string $tenantId, GetTenantOnboardingProgressAction $progress): JsonResponse
    {
        $tenant = $request->attributes->get('active_tenant');
        $member = TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();

        abort_unless($member->role === TenantUserRole::Owner, 403);

        $result = $progress->execute($tenant);
        $incomplete = array_keys(array_filter(
            $result['steps'],
            static fn (array $step): bool => ! $step['complete']
        ));

        if ($incomplete !== []) {
            throw ValidationException::withMessages([
                'onboarding' => 'Conclua as configurações pendentes: '.implode(', ', $incomplete).'.',
            ]);
        }

        if ($tenant->onboarding_completed_at === null) {
            $tenant->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return response()->json(['data' => $progress->execute($tenant->fresh())]);
    }
}
