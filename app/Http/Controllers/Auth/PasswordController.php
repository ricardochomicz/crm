<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function sendLink(Request $request): Response
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        Password::broker('users')->sendResetLink($credentials);

        return response()->noContent();
    }

    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'max:128',
                PasswordRule::min(8),
            ],
        ]);

        $status = Password::broker('users')->reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'O link é inválido ou expirou. Solicite outro link.',
            ]);
        }

        return redirect()->route('password.reset', [
            'token' => 'concluido',
        ])->with('status', 'Senha definida. Você já pode entrar no TelecomHub.');
    }
}
