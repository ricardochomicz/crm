<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class RegisterController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        RegisterRequest $request,
        RegisterUserAction $registerUserAction
    ): Response {
        $user = $registerUserAction->handle(
            $request->safe()->only([
                'name',
                'email',
                'password',
                'subscription_plan_id',
                'tenant'
            ])
        );

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return response('', Response::HTTP_CREATED);
    }
}
