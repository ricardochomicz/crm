<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('role', 20);
            $table->json('permissions');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'role']);
        });
        Schema::create('tenant_permission_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20);
            $table->json('previous_permissions');
            $table->json('permissions');
            $table->unsignedInteger('version');
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_permission_changes');
        Schema::dropIfExists('tenant_role_permissions');
    }
};
