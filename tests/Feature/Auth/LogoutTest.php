<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_logs_out_user_clears_session_and_rotates_security_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');
        $this->withSession([
            'logout_test_marker' => 'dados-da-sessao-anterior',
        ]);

        $session = $this->app['session.store'];
        $previousSessionId = $session->getId();
        $previousCsrfToken = $session->token();

        $response = $this->postJson(route('api.v1.auth.logout'));

        $response->assertNoContent();
        $response->assertSessionMissing('logout_test_marker');

        $this->assertGuest('web');
        $this->assertNotSame($previousSessionId, $session->getId());
        $this->assertNotEmpty($session->token());
        $this->assertNotSame($previousCsrfToken, $session->token());
    }

    public function test_returns_401_for_me_after_session_logout(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertNoContent();

        $this->assertAuthenticatedAs($user, 'web');

        $this->postJson(route('api.v1.auth.logout'))
            ->assertNoContent();

        Auth::forgetGuards();

        $this->getJson(route('api.v1.me'))
            ->assertUnauthorized();

        $this->assertGuest('web');
    }

    public function test_returns_401_when_logging_out_without_authentication(): void
    {
        $response = $this->postJson(route('api.v1.auth.logout'));

        $response->assertUnauthorized();

        $this->assertGuest('web');
    }
}
