<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\DeleteClientAction;
use App\Actions\Client\FilterClientsAction;
use App\Actions\Client\ListClientOptionsAction;
use App\Actions\Client\ListClientContractRenewalsAction;
use App\Actions\Client\SaveClientAction;
use App\Actions\Client\SaveClientContactAction;
use App\Actions\Client\ShowClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeleteClientRequest;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\SaveClientContactRequest;
use App\Http\Requests\Client\SaveClientRequest;
use App\Http\Requests\Client\ViewClientRequest;
use App\Http\Resources\Client\ClientContactResource;
use App\Http\Resources\Client\ClientResource;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function index(
        IndexClientRequest $request,
        string $tenantId,
        FilterClientsAction $action
    ) {
        $tenant = $request->attributes->get('active_tenant');
        $filters = $request->validated();
        $clients = isset($filters['per_page'])
            ? $action->paginate($tenant->id, $filters, $request->user()->id)
            : $action->execute($tenant->id, $filters, $request->user()->id);

        return ClientResource::collection($clients)->additional([
            'meta' => [
                'can_manage' => $request->user()->can('updateClients', $tenant),
                'can_create' => $request->user()->can('createClients', $tenant),
                'can_delete' => $request->user()->can('deleteClients', $tenant),
            ],
        ]);
    }

    public function show(
        ViewClientRequest $request,
        string $tenantId,
        string $clientId,
        ShowClientAction $action,
        ListClientContractRenewalsAction $renewals
    ): ClientResource {
        $client = $action->execute(
            $request->attributes->get('active_tenant')->id,
            $clientId,
            $request->user()->id
        );

        $client->setAttribute('contract_renewals', $renewals->execute($client));

        return new ClientResource($client);
    }

    public function options(
        ViewClientRequest $request,
        string $tenantId,
        ListClientOptionsAction $action
    ): array {
        return ['data' => $action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->user()->id
        )];
    }

    public function destroy(
        DeleteClientRequest $request,
        string $tenantId,
        string $clientId,
        DeleteClientAction $action
    ): Response {
        $action->execute($request->attributes->get('active_tenant')->id, $clientId, $request->user()->id);

        return response()->noContent();
    }

    public function store(
        SaveClientRequest $request,
        string $tenantId,
        SaveClientAction $action
    ): ClientResource {
        return new ClientResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->user()->id,
            $request->validatedClient()
        ));
    }

    public function update(
        SaveClientRequest $request,
        string $tenantId,
        string $clientId,
        SaveClientAction $action
    ): ClientResource {
        return new ClientResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->user()->id,
            $request->validatedClient(),
            $clientId
        ));
    }

    public function saveContact(
        SaveClientContactRequest $request,
        string $tenantId,
        string $clientId,
        SaveClientContactAction $action,
        ?string $contactId = null
    ): ClientContactResource {
        return new ClientContactResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $clientId,
            $request->validated(),
            $request->user()->id,
            $contactId
        ));
    }
}
