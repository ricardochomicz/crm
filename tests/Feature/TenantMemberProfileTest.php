<?php

namespace Tests\Feature;

use App\Actions\Tenant\FindTenantMemberProfileAction;
use App\Actions\Tenant\UpdateTenantMemberProfileAction;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantMemberProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function membership(Tenant $tenant, ?User $user = null): TenantUser
    {
        $user ??= User::factory()->create();
        $user->tenants()->attach($tenant->id, [
            'role' => 'CONSULTANT',
            'status' => 'ACTIVE',
        ]);

        return TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)->firstOrFail();
    }

    public function test_profile_is_isolated_by_tenant_even_when_user_is_shared(): void
    {
        $user = User::factory()->create();
        $firstTenant = Tenant::factory()->create();
        $secondTenant = Tenant::factory()->create();
        $first = $this->membership($firstTenant, $user);
        $second = $this->membership($secondTenant, $user);

        app(UpdateTenantMemberProfileAction::class)->execute($first, [
            'cpf' => '52998224725',
            'birth_date' => '1990-05-10',
            'mobile_phone' => '41999999999',
        ]);

        $this->assertSame('52998224725', $first->fresh()->cpf);
        $this->assertNull($second->fresh()->cpf);
        $this->assertNull($second->fresh()->birth_date);
        $this->assertNull($second->fresh()->mobile_phone);
        $this->expectException(ModelNotFoundException::class);
        app(FindTenantMemberProfileAction::class)->execute($secondTenant, $first->id);
    }

    public function test_cpf_is_encrypted_at_rest(): void
    {
        $member = $this->membership(Tenant::factory()->create());
        app(UpdateTenantMemberProfileAction::class)->execute($member, ['cpf' => '52998224725']);
        $raw = DB::table('tenant_users')->where('id', $member->id)->value('cpf');

        $this->assertNotSame('52998224725', $raw);
        $this->assertSame('52998224725', $member->fresh()->cpf);
    }

    public function test_profile_permissions_are_registered_in_catalog(): void
    {
        $keys = TenantPermission::keys();
        $this->assertContains('members.profile.view', $keys);
        $this->assertContains('members.profile.update', $keys);
    }
}
