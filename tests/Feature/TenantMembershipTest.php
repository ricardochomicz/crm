<?php

namespace Tests\Feature;

use App\Enums\TenantUserRole;
use App\Enums\TenantUserStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_membership_with_uuid_and_casts(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $tenant->users()->attach($user->id, [
            'role' => TenantUserRole::Owner->value,
            'status' => TenantUserStatus::Active->value,
            'joined_at' => '2026-10-29 12:00:00',
        ]);

        $member = $tenant->users()->sole();
        $membership = $member->membership;

        $this->assertTrue(Str::isUuid($tenant->id));
        $this->assertTrue(Str::isUuid($membership->id));
        $this->assertSame($user->id, $member->id);
        $this->assertInstanceOf(TenantUser::class, $membership);
        $this->assertSame(TenantUserRole::Owner, $membership->role);
        $this->assertSame(TenantUserStatus::Active, $membership->status);
        $this->assertInstanceOf(Carbon::class, $membership->joined_at);
        $this->assertSame(
            '2026-10-29 12:00:00',
            $membership->joined_at->format('Y-m-d H:i:s'),
        );

        $this->assertDatabaseHas('tenant_users', [
            'id' => $membership->id,
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'OWNER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_user_has_independent_roles_and_statuses_in_two_tenants(): void
    {
        $user = User::factory()->create();
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();

        $user->tenants()->attach($firstTenant->id, [
            'role' => TenantUserRole::Owner->value,
            'status' => TenantUserStatus::Active->value,
        ]);

        $user->tenants()->attach($secondTenant->id, [
            'role' => TenantUserRole::Consultant->value,
            'status' => TenantUserStatus::Suspended->value,
        ]);

        $tenants = $user->tenants()->get()->keyBy('id');

        $this->assertCount(2, $tenants);
        $this->assertSame(
            TenantUserRole::Owner,
            $tenants[$firstTenant->id]->membership->role,
        );
        $this->assertSame(
            TenantUserStatus::Active,
            $tenants[$firstTenant->id]->membership->status,
        );
        $this->assertSame(
            TenantUserRole::Consultant,
            $tenants[$secondTenant->id]->membership->role,
        );
        $this->assertSame(
            TenantUserStatus::Suspended,
            $tenants[$secondTenant->id]->membership->status,
        );
    }

    public function test_rejects_duplicate_membership_in_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $tenant->users()->attach($user->id, [
            'role' => TenantUserRole::Owner->value,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $tenant->users()->attach($user->id, [
            'role' => TenantUserRole::Consultant->value,
        ]);
    }
}
