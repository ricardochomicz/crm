<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_member_team_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->uuid('membership_id');
            $table->uuid('previous_team_id')->nullable();
            $table->uuid('new_team_id')->nullable();

            $table->foreignId('actor_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('reason');
            $table->timestamp('created_at');

            $table->foreign(
                ['tenant_id', 'membership_id'],
                'member_team_changes_membership_foreign',
            )
                ->references(['tenant_id', 'id'])
                ->on('tenant_users')
                ->restrictOnDelete();

            $table->foreign(
                ['tenant_id', 'previous_team_id'],
                'member_team_changes_previous_team_foreign',
            )
                ->references(['tenant_id', 'id'])
                ->on('teams')
                ->restrictOnDelete();

            $table->foreign(
                ['tenant_id', 'new_team_id'],
                'member_team_changes_new_team_foreign',
            )
                ->references(['tenant_id', 'id'])
                ->on('teams')
                ->restrictOnDelete();

            $table->index(
                ['tenant_id', 'membership_id', 'created_at', 'id'],
                'member_team_changes_history_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_member_team_changes');
    }
};
