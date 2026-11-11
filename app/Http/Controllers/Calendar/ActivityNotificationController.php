<?php

namespace App\Http\Controllers\Calendar;

use App\Events\ActivityNotificationChanged;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityNotificationController extends Controller
{
    private function membership(Request $request): object
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('viewCalendar', $tenant), 403);
        return DB::table('tenant_users')->where('tenant_id', $tenant->id)
            ->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $member = $this->membership($request);
        $tenantId = $request->attributes->get('active_tenant')->id;
        $query = DB::table('activity_notifications')->where('tenant_id', $tenantId)->where('tenant_user_id', $member->id);
        return response()->json([
            'unread' => (clone $query)->whereNull('read_at')->count(),
            'data' => $query->orderByDesc('created_at')->limit(50)->get(['id', 'kind', 'title', 'message', 'calendar_event_id', 'read_at', 'created_at']),
        ]);
    }

    public function read(Request $request, string $tenantId, string $notificationId): JsonResponse
    {
        $member = $this->membership($request);
        $updated = DB::table('activity_notifications')->where('tenant_id', $tenantId)
            ->where('tenant_user_id', $member->id)->where('id', $notificationId)
            ->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        if ($updated) ActivityNotificationChanged::dispatch($tenantId, $member->id);
        return response()->json(['ok' => true]);
    }
}
