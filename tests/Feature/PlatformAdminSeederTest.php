<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAdminSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_does_not_create_users_without_explicit_credentials(): void
    {
        config(['platform.admin' => ['name' => null, 'email' => null, 'password' => null]]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('subscription_plans', 3);
    }

    public function test_creates_platform_admin_with_hashed_password_and_is_repeatable(): void
    {
        config(['platform.admin' => [
            'name' => 'Administrador', 'email' => 'master@example.test', 'password' => 'test-password-123',
        ]]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'master@example.test')->firstOrFail();
        $this->assertTrue($user->is_platform_admin);
        $this->assertTrue(Hash::check('test-password-123', $user->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_does_not_promote_or_reset_an_existing_user(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $passwordHash = $user->password;
        config(['platform.admin' => [
            'name' => 'Administrador', 'email' => $user->email, 'password' => 'test-password-123',
        ]]);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse($user->fresh()->is_platform_admin);
        $this->assertSame($passwordHash, $user->fresh()->password);
    }
}
