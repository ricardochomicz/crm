<?php

namespace App\Http\Controllers\Tenant\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantLogoRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UpdateTenantLogoController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(UpdateTenantLogoRequest $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()?->can('update', $tenant), 403);

        $request->validated();

        $path = $request->file('logo')->store('tenant-logos/'.$tenant->id, 'public');
        if (! $path) {
            throw ValidationException::withMessages(['logo' => 'Não foi possível armazenar a imagem.']);
        }

        $old = $tenant->logo;
        $tenant->update(['logo' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return ['data' => ['logo_url' => Storage::disk('public')->url($path)]];
    }
}
