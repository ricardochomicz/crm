<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\ScopeVisibleClientsAction;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientOperatorClassification;
use App\Models\ClientOperatorHistory;
use App\Models\OperatorClassification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientOperatorClassificationController extends Controller
{
    public function update(Request $request, string $tenantId, string $clientId, int $operatorId, ScopeVisibleClientsAction $visibility)
    {
        $tenant = $request->attributes->get('active_tenant');
        abort_unless($request->user()->can('updateClients', $tenant) && $tenant->access?->allows_operational_writes, 403);
        $client = $visibility->execute(Client::query(), $tenant->id, $request->user()->id)->findOrFail($clientId);
        $data = $request->validate([
            'classification_id' => ['required', 'integer', Rule::exists('operator_classifications', 'id')
                ->where('operator_id', $operatorId)
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))],
        ]);
        DB::transaction(function () use ($tenant, $client, $operatorId, $data, $request) {
            $assignment = ClientOperatorClassification::query()->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)->where('operator_id', $operatorId)->lockForUpdate()->first();
            if ($assignment && (int) $assignment->classification_id === (int) $data['classification_id']) return;
            ClientOperatorClassification::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'client_id' => $client->id, 'operator_id' => $operatorId],
                ['classification_id' => $data['classification_id']]
            );
            ClientOperatorHistory::create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id,
                'operator_id' => $operatorId, 'classification_id' => $data['classification_id'],
                'changed_at' => now(), 'changed_by' => $request->user()->id,
            ]);
        });
        return response()->json(['data' => ['operator_id' => $operatorId, 'classification_id' => $data['classification_id']]]);
    }
}
