<?php

namespace App\Http\Controllers\Client;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantPermission;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientDocumentController extends Controller
{
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    private function access(Request $request, string $clientId, bool $write = false): array
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant, 403);
        $member = $this->permissions->membership($request->user(), $tenant);
        abort_unless($member && $this->permissions->allows($request->user(), $tenant, $write ? TenantPermission::UpdateClients : TenantPermission::ViewClients), 403);
        $client = Client::query()->where('tenant_id', $tenant->id)->findOrFail($clientId);
        if (! in_array($member->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            abort_unless(
                DB::table('client_sellers')->where('tenant_id', $tenant->id)->where('client_id', $client->id)
                    ->where('tenant_user_id', $member->id)->whereNull('ended_at')->exists(),
                403
            );
        }
        return [$tenant, $member, $client];
    }

    public function index(Request $request, string $tenantId, string $clientId): JsonResponse
    {
        [$tenant] = $this->access($request, $clientId);
        $rows = DB::table('client_documents')->where('tenant_id', $tenant->id)->where('client_id', $clientId)
            ->orderByDesc('created_at')->get(['id', 'category', 'original_name', 'mime_type', 'size_bytes', 'created_at']);
        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, string $tenantId, string $clientId): JsonResponse
    {
        [$tenant, $member] = $this->access($request, $clientId, true);
        $data = $request->validate([
            'category' => ['required', Rule::in(['contrato_social', 'fatura', 'documento', 'outro'])],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp'],
        ]);
        $file = $data['file'];
        $id = (string) Str::uuid();
        $path = $file->store("tenants/{$tenant->id}/clients/{$clientId}/documents", 'local');
        try {
            DB::table('client_documents')->insert([
                'id' => $id, 'tenant_id' => $tenant->id, 'client_id' => $clientId,
                'tenant_user_id' => $member->id, 'category' => $data['category'],
                'original_name' => $file->getClientOriginalName(), 'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function download(Request $request, string $tenantId, string $clientId, string $documentId): StreamedResponse
    {
        [$tenant] = $this->access($request, $clientId);
        $document = DB::table('client_documents')->where('tenant_id', $tenant->id)
            ->where('client_id', $clientId)->where('id', $documentId)->first();
        abort_unless($document && Storage::disk('local')->exists($document->storage_path), 404);
        return Storage::disk('local')->download($document->storage_path, $document->original_name);
    }
}
