<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_member_team_changes', function (Blueprint $table): void {
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite'
                ? ['tenant_id', 'membership_id']
                : 'member_team_changes_membership_foreign');
        });

        Schema::create('tenant_member_departures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->uuid('membership_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_member_departures');

        Schema::table('tenant_member_team_changes', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'membership_id'], 'member_team_changes_membership_foreign')
                ->references(['tenant_id', 'id'])->on('tenant_users')->restrictOnDelete();
        });
    }
};
