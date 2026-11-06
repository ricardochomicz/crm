<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperatorListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_search_returns_only_matching_active_operators(): void
    {
        $user = User::factory()->create();
        $operator = Operator::query()->create(['name' => 'Example Telecom', 'slug' => 'example', 'is_active' => true]);
        Operator::query()->create(['name' => 'Example Archived', 'slug' => 'archived', 'is_active' => false]);
        Operator::query()->create(['name' => 'Other Telecom', 'slug' => 'other', 'is_active' => true]);

        $this->actingAs($user)->getJson('/api/v1/operators?search=Example&is_active=0')
            ->assertOk()->assertExactJson(['data' => [[
                'id' => $operator->id,
                'name' => 'Example Telecom',
                'slug' => 'example',
                'is_active' => true,
            ]]]);
    }

    public function test_invalid_search_returns_422(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/operators?search[]=invalid')
            ->assertUnprocessable()->assertInvalid(['search']);
    }

    public function test_guest_receives_401(): void
    {
        $this->getJson('/api/v1/operators')->assertUnauthorized();
    }
}
