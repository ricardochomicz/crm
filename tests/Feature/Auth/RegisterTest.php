<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_registers_and_authenticates_user(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Silva', 'email' => 'ana@example.test',
            'password' => 'senha-segura', 'password_confirmation' => 'senha-segura',
        ])->assertCreated();

        $user = User::query()->where('email', 'ana@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('senha-segura', $user->password));
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.name', 'Ana Silva');
    }

    public function test_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Outra pessoa', 'email' => $user->email,
            'password' => 'senha-segura', 'password_confirmation' => 'senha-segura',
        ])->assertUnprocessable()->assertInvalid(['email']);
        $this->assertGuest();
    }
}
