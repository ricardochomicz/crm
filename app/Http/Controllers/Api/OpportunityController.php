<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function index()
    {
        $client = Opportunity::with('client')->get();
        return response()->json($client);
    }


    public function store(Request $request)
    {
        $opportunity = Opportunity::create($request->all());
        $opportunity->refresh();
        return new OpportunityResource($opportunity);
    }


    public function show(Opportunity $opportunity)
    {
        return new OpportunityResource($opportunity);
    }


    public function update(Request $request, Opportunity $opportunity)
    {
        $opportunity->fill($request->all());
        $opportunity->touch();
        $opportunity->save();
        return new OpportunityResource($opportunity);
    }


    public function destroy(Opportunity $opportunity)
    {
        //
    }

    public function getOpportunityClient($value){
        return Opportunity::with('client')->where('client', '=', $value)->get();
    }

}
