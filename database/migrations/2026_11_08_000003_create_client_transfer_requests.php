<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_transfer_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('from_tenant_user_id')->constrained('tenant_users');
            $table->foreignUuid('to_tenant_user_id')->constrained('tenant_users');
            $table->foreignUuid('requested_by')->constrained('tenant_users');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('tenant_users');
            $table->string('status', 20)->default('PENDING');
            $table->text('reason')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_transfer_requests');
    }
};
