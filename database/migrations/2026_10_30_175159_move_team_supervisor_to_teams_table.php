<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            DB::table('teams')->exists()
            || DB::table('tenant_users')->whereNotNull('team_role')->exists()
        ) {
            throw new RuntimeException(
                'Existem dados de equipes. É necessário convertê-los antes desta migration.',
            );
        }

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'id'],
                'tenant_users_tenant_id_id_unique',
            );
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->uuid('supervisor_membership_id');

            $table->foreign(
                ['tenant_id', 'supervisor_membership_id'],
                'teams_supervisor_tenant_foreign',
            )
                ->references(['tenant_id', 'id'])
                ->on('tenant_users')
                ->restrictOnDelete();
        });

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->dropColumn('team_role');
        });
    }

    public function down(): void
    {
        if (DB::table('teams')->exists()) {
            throw new RuntimeException(
                'O rollback exige conversão dos dados de supervisão existentes.',
            );
        }

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->enum('team_role', ['SUPERVISOR', 'MEMBER'])->nullable();
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropForeign('teams_supervisor_tenant_foreign');
            $table->dropColumn('supervisor_membership_id');
        });

        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->dropUnique('tenant_users_tenant_id_id_unique');
        });
    }
};
