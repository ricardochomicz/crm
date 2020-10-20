<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{

    public function index()
    {
        return User::orderBy('active', 'DESC')->orderBy('name', 'ASC')->get();
    }


    public function store(Request $request)
    {
        $user = User::create($request->all());
        $user->refresh();
        return new UserResource($user);
    }


    public function show(User $user)
    {
        return new UserResource($user);
    }


    public function update(Request $request, User $user)
    {
        $user->fill($request->all());
        $user->save();
        return new UserResource($user);
    }


    public function destroy(User $user)
    {
        $user->delete();
        return response()->json([], 204);
    }
}
