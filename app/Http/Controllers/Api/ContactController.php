<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{

    public function index()
    {
        //
    }


    public function store(Request $request)
    {
        $contact = Contact::create($request->all());
        $contact->refresh();
        return new ContactResource($contact);
    }


    public function show(Contact $contact)
    {
        //
    }


    public function update(Request $request, Contact $contact)
    {
        $contact->fill($request->all());
        $contact->save();
        return new ContactResource($contact);
    }


    public function destroy(Contact $contact)
    {
        $contact->delete();
        return response()->json([], 204);
    }

    public function getCli($value)
    {
        $contact = Contact::where('client', '=', $value)->get();
        return ContactResource::collection($contact);

    }
}
