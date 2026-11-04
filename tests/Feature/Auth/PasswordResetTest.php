<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resets_password_and_rejects_reusing_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('users')->createToken($user);
        $data = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ];

        $this->postJson('/api/v1/auth/password/reset', $data)->assertNoContent();
        $this->assertTrue(Hash::check('nova-senha-segura', $user->fresh()->password));
        $this->assertGuest();
        $this->postJson('/api/v1/auth/password/reset', $data)
            ->assertUnprocessable()->assertInvalid(['email']);
    }

    public function test_rejects_invalid_token_without_changing_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'invalid-token', 'email' => $user->email,
            'password' => 'nova-senha-segura', 'password_confirmation' => 'nova-senha-segura',
        ])->assertUnprocessable()->assertInvalid(['email']);

        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('users')->createToken($user);
        $this->travel(61)->minutes();

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token, 'email' => $user->email,
            'password' => 'nova-senha-segura', 'password_confirmation' => 'nova-senha-segura',
        ])->assertUnprocessable()->assertInvalid(['email']);
    }

    public function test_rejects_password_confirmation_mismatch(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('users')->createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token, 'email' => $user->email,
            'password' => 'nova-senha-segura', 'password_confirmation' => 'outra-senha',
        ])->assertUnprocessable()->assertInvalid(['password']);
    }

    public function test_recovery_does_not_disclose_whether_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/password/email', ['email' => $user->email])->assertNoContent();
        $this->postJson('/api/v1/auth/password/email', ['email' => 'missing@example.test'])->assertNoContent();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_redirects_to_configured_frontend(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $this->get('/reset-password/example-token?email=person%40example.test')
            ->assertRedirect('https://app.example.test/reset-password/example-token?email=person%40example.test')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_keeps_existing_view_when_frontend_is_not_configured(): void
    {
        config(['app.frontend_url' => null]);

        $this->get('/reset-password/example-token?email=person%40example.test')
            ->assertOk()->assertViewIs('auth.reset-password');
    }
}
