<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpportunityTermResource;
use App\Models\Opportunity;
use App\Models\OpportunityTerm;
use Illuminate\Http\Request;

class OpportunityTermController extends Controller
{

    public function index(Opportunity $opportunity)
    {
        return $opportunity->terms();
    }


    public function store(Request $request, Opportunity $opportunity)
    {
        return OpportunityTerm::createTermFiles($opportunity->id, $request->terms);
    }


    public function show(Opportunity $opportunity, OpportunityTerm $term)
    {
        if ($term->opportunity != $opportunity->id) {
            abort(404);
        }
        return new OpportunityTermResource($term);
    }


    public function update(Request $request, OpportunityTerm $opportunityTerm)
    {
        //
    }


    public function destroy(OpportunityTerm $opportunityTerm)
    {
        //
    }
}
