<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->uuid('team_id')->nullable();
            $table->enum('team_role', ['SUPERVISOR', 'MEMBER'])->nullable();

            $table->foreign(
                ['tenant_id', 'team_id'],
                'tenant_users_team_tenant_foreign',
            )
                ->references(['tenant_id', 'id'])
                ->on('teams')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->dropForeign('tenant_users_team_tenant_foreign');
            $table->dropColumn(['team_id', 'team_role']);
        });

        Schema::dropIfExists('teams');
    }
};
