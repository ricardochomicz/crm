<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Opportunity;
use Illuminate\Http\Request;

class ClientController extends Controller
{

    public function index()
    {
        $client = Client::with('opportunities', 'contacts')->get();
        return response()->json($client);
    }


    public function store(Request $request)
    {
        $client = Client::create($request->all());
        $client->refresh();
        return new ClientResource($client);
    }


    public function show(Client $client)
    {
        return new ClientResource($client);
    }


    public function update(Request $request, Client $client)
    {
        $client->fill($request->all());
        $client->save();
        return new ClientResource($client);
    }


    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json([], 204);
    }

    public function document($value)
    {
        $cliente = Client::where('document', $value)->first();
        if ($cliente) return new ClientResource($cliente);
    }

    public function getClient($value)
    {
        $client = Client::where('name', 'LIKE', "%$value%")
            ->orWhere('document', 'LIKE', "%$value%")->get();
        return response()->json($client);

    }
}
