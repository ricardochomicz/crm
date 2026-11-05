<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name', 180);
            $table->boolean('is_headquarters')->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'is_headquarters']);
        });
        Schema::create('tenant_unit_memberships', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->uuid('tenant_unit_id');
            $table->uuid('membership_id');
            $table->primary(['tenant_unit_id', 'membership_id']);
            $table->foreign(['tenant_id', 'tenant_unit_id'], 'unit_memberships_unit_foreign')->references(['tenant_id', 'id'])->on('tenant_units')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'membership_id'], 'unit_memberships_member_foreign')->references(['tenant_id', 'id'])->on('tenant_users')->cascadeOnDelete();
        });
        Schema::table('teams', function (Blueprint $table): void {
            $table->uuid('tenant_unit_id')->nullable();
            $table->foreign(['tenant_id', 'tenant_unit_id'], 'teams_unit_foreign')->references(['tenant_id', 'id'])->on('tenant_units')->restrictOnDelete();
        });
        DB::table('tenants')->orderBy('id')->chunkById(100, function ($tenants): void {
            foreach ($tenants as $tenant) {
                $unitId = (string) Str::uuid();
                DB::table('tenant_units')->insert([
                    'id' => $unitId, 'tenant_id' => $tenant->id, 'name' => 'Matriz', 'is_headquarters' => true,
                    'email' => $tenant->email, 'phone' => $tenant->phone, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('teams')->where('tenant_id', $tenant->id)->update(['tenant_unit_id' => $unitId]);
                DB::table('tenant_users')->where('tenant_id', $tenant->id)->orderBy('id')->chunkById(100, function ($members) use ($tenant, $unitId): void {
                    DB::table('tenant_unit_memberships')->insert($members->map(fn ($member): array => [
                        'tenant_id' => $tenant->id, 'tenant_unit_id' => $unitId, 'membership_id' => $member->id,
                    ])->all());
                });
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['tenant_id', 'tenant_unit_id'] : 'teams_unit_foreign');
            $table->dropColumn('tenant_unit_id');
        });
        Schema::dropIfExists('tenant_unit_memberships');
        Schema::dropIfExists('tenant_units');
    }
};
