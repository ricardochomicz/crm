<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticates_user_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('senha-de-teste')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-de-teste'
        ]);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_rejects_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('senha-correta')
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-incorreta'
        ]);

        $response->assertUnprocessable();
        $response->assertInvalid([
            'email' => 'E-mail ou senha inválidos.'
        ]);
        $this->assertGuest('web');
    }

    public function test_rejects_unknown_email_with_same_error_message(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inexistente@example.test',
            'password' => 'senha-de-teste',
        ]);

        $response->assertUnprocessable();
        $response->assertInvalid([
            'email' => 'E-mail ou senha inválidos.',
        ]);
        $this->assertGuest('web');
    }

    public function test_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email', 'password']);
        $this->assertGuest('web');
    }

    public function test_blocks_sixth_attempt_for_same_email_and_ip(): void
    {
        $this->freezeTime();

        $credentials = [
            'email' => 'inexistente@example.test',
            'password' => 'senha-incorreta',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $credentials)
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', $credentials)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertGuest('web');

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/login', $credentials)
            ->assertUnprocessable();
    }

    public function test_blocks_ip_after_thirty_attempts_with_different_emails(): void
    {
        $this->freezeTime();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => "usuario{$attempt}@example.test",
                'password' => 'senha-incorreta',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'outro@example.test',
            'password' => 'senha-incorreta',
        ])->assertTooManyRequests();

        $this->assertGuest('web');
    }
}
