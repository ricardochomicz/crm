<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
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
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $frontendUrl = config('app.frontend_url');

        if (is_string($frontendUrl) && $frontendUrl !== '' && $token !== 'concluido') {
            return redirect()->away(
                rtrim($frontendUrl, '/').'/reset-password/'.rawurlencode($token)
                .'?'.http_build_query(['email' => (string) $request->string('email')]),
            )->withHeaders([
                'Referrer-Policy' => 'no-referrer',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    public function sendLink(SendPasswordResetLinkRequest $request): Response
    {
        $credentials = $request->validated();

        Password::broker('users')->sendResetLink($credentials);

        return response()->noContent();
    }

    public function update(ResetPasswordRequest $request): RedirectResponse|Response
    {
        $credentials = $request->validated();

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

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('password.reset', [
            'token' => 'concluido',
        ])->with('status', 'Senha definida. Você já pode entrar no TelecomHub.');
    }
}
