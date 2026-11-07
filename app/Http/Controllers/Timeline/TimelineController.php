<?php
namespace App\Http\Controllers\Timeline;

use App\Actions\Timeline\TimelineAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineController extends Controller {
    private function tenant(Request $request): Tenant {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($tenant instanceof Tenant, 403);
        return $tenant;
    }

    public function index(Request $request, string $tenantId, string $type, string $id, TimelineAction $action): JsonResponse {
        return response()->json($action->list($this->tenant($request), $request->user(), $type, $id));
    }

    public function store(Request $request, string $tenantId, string $type, string $id, TimelineAction $action): JsonResponse {
        $data = $request->validate(['content' => ['required', 'string', 'max:10000']]);
        return response()->json(['data' => $action->create($this->tenant($request), $request->user(), $type, $id, $data['content'])], 201);
    }

    public function update(Request $request, string $tenantId, string $id, TimelineAction $action): JsonResponse {
        $data = $request->validate(['content' => ['required', 'string', 'max:10000']]);
        return response()->json(['data' => $action->update($this->tenant($request), $request->user(), $id, $data['content'])]);
    }

    public function destroy(Request $request, string $tenantId, string $id, TimelineAction $action): \Illuminate\Http\Response {
        $action->delete($this->tenant($request), $request->user(), $id);
        return response()->noContent();
    }

    public function upload(Request $request, string $tenantId, string $id, TimelineAction $action): JsonResponse {
        $data = $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp']]);
        return response()->json(['data' => $action->attach($this->tenant($request), $request->user(), $id, $data['file'])], 201);
    }

    public function download(Request $request, string $tenantId, string $id, TimelineAction $action): \Symfony\Component\HttpFoundation\StreamedResponse {
        return $action->download($this->tenant($request), $request->user(), $id);
    }
}
