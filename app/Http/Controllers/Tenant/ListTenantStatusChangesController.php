<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTenantStatusChangesRequest;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class ListTenantStatusChangesController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        ListTenantStatusChangesRequest $request,
        Tenant $tenant,
    ): array {
        $filters = $request->validated();

        return DB::table('tenant_status_changes as changes')
            ->join('users as actors', 'actors.id', '=', 'changes.actor_user_id')
            ->where('changes.tenant_id', $tenant->getKey())
            ->select([
                'changes.id',
                'changes.tenant_id',
                'changes.previous_status',
                'changes.new_status',
                'changes.reason',
                'changes.created_at',
                'changes.actor_user_id',
                'actors.name as actor_name',
            ])
            ->orderByDesc('changes.created_at')
            ->orderByDesc('changes.id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($filters)
            ->through(fn (stdClass $change): array => [
                'id' => $change->id,
                'tenant_id' => $change->tenant_id,
                'previous_status' => $change->previous_status,
                'new_status' => $change->new_status,
                'reason' => $change->reason,
                'actor' => [
                    'id' => (int) $change->actor_user_id,
                    'name' => $change->actor_name,
                ],
                'created_at' => Carbon::parse($change->created_at)
                    ->toIso8601String(),
            ])
            ->toArray();
    }
}