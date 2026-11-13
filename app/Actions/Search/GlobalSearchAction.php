<?php

namespace App\Actions\Search;

use App\Actions\Client\ScopeVisibleClientsAction;
use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantUserRole;
use App\Models\Client;
use App\Models\CommercialOrder;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GlobalSearchAction
{
    public function __construct(
        private ScopeVisibleClientsAction $visibleClients,
        private ResolveTenantPermissionsAction $permissions,
    ) {}

    public function execute(Tenant $tenant, User $user, string $term): array
    {
        $actor = $this->permissions->membership($user, $tenant) ?? abort(403);
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $digits = preg_replace('/\D/', '', $term);
        $results = ['clients' => [], 'opportunities' => [], 'orders' => []];

        if ($user->can('viewClients', $tenant)) {
            $results['clients'] = $this->visibleClients
                ->execute(Client::query(), $tenant->id, $user->id)
                ->where(function ($query) use ($like, $digits): void {
                    $query->where('company_name', 'like', $like);
                    if (strlen($digits) >= 3) {
                        $query->orWhere('document', 'like', '%' . $digits . '%')
                            ->orWhere('phone', 'like', '%' . $digits . '%');
                    }
                })
                ->orderBy('company_name')->limit(6)->get(['id', 'company_name', 'document'])
                ->map(fn ($item) => [
                    'id' => $item->id, 'title' => $item->company_name,
                    'subtitle' => $item->document, 'type' => 'clients',
                ])->all();
        }

        if ($user->can('viewOpportunities', $tenant)) {
            $query = Opportunity::query()->where('tenant_id', $tenant->id);
            if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
                $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                    ->where('supervisor_membership_id', $actor->id)->pluck('id');
                $members = TenantUser::query()->where('tenant_id', $tenant->id)
                    ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
                $query->whereIn('tenant_user_id', $members);
            }
            $results['opportunities'] = $query
                ->where(function ($q) use ($like): void {
                    $q->where('title', 'like', $like)->orWhere('code', 'like', $like)
                        ->orWhereHas('client', fn ($client) => $client->where('company_name', 'like', $like));
                })
                ->latest()->limit(6)->get(['id', 'title', 'code', 'status'])
                ->map(fn ($item) => [
                    'id' => $item->id, 'title' => $item->title,
                    'subtitle' => $item->code . ' · ' . $item->status, 'type' => 'opportunities',
                ])->all();
        }

        if ($user->can('viewOrders', $tenant)) {
            $query = CommercialOrder::query()->where('tenant_id', $tenant->id);
            if (! in_array($actor->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
                $teams = DB::table('teams')->where('tenant_id', $tenant->id)
                    ->where('supervisor_membership_id', $actor->id)->pluck('id');
                $members = TenantUser::query()->where('tenant_id', $tenant->id)
                    ->whereIn('team_id', $teams)->pluck('id')->push($actor->id)->unique()->all();
                $query->whereIn('tenant_user_id', $members);
            }
            $results['orders'] = $query->where(function ($q) use ($like): void {
                $q->where('code', 'like', $like)->orWhere('customer_name', 'like', $like);
            })->latest()->limit(6)->get(['id', 'code', 'customer_name', 'status'])
                ->map(fn ($item) => [
                    'id' => $item->id, 'title' => $item->code ?: $item->customer_name,
                    'subtitle' => $item->customer_name . ' · ' . $item->status, 'type' => 'orders',
                ])->all();
        }

        return $results;
    }
}
