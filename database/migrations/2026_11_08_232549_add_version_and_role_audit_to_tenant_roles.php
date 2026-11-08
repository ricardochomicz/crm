<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_roles', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
        });

        Schema::table('tenant_permission_changes', function (Blueprint $table): void {
            $table->foreignUuid('tenant_role_id')->nullable()->constrained('tenant_roles')->nullOnDelete();
            $table->json('previous_role_state')->nullable();
            $table->json('role_state')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_permission_changes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_role_id');
            $table->dropColumn(['previous_role_state', 'role_state']);
        });
        Schema::table('tenant_roles', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
