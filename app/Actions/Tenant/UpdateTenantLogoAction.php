<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateTenantLogoAction
{
    public function execute(Tenant $tenant, UploadedFile $logo): Tenant
    {
        $path = $logo->store('tenant-logos/'.$tenant->getKey(), 'public');

        if (! $path) {
            throw ValidationException::withMessages([
                'logo' => 'Não foi possível armazenar a imagem.',
            ]);
        }

        $oldPath = $tenant->logo;

        try {
            $tenant->update(['logo' => $path]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $tenant;
    }
}
