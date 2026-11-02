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
        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('invited_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('email', 254);

            $table->enum('role', [
                'MANAGER',
                'CONSULTANT',
                'ASSISTANT',
            ]);

            $table->enum('status', [
                'PENDING',
                'ACCEPTED',
                'REVOKED',
            ])->default('PENDING');

            $table->char('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(
                ['tenant_id', 'status', 'expires_at'],
                'tenant_invitations_seats_index',
            );

            $table->index(
                ['tenant_id', 'email', 'status'],
                'tenant_invitations_email_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
