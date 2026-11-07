<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignUuid('created_by_tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->string('priority', 20)->default('normal');
            $table->boolean('all_day')->default(false);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_at', 'ends_at']);
            $table->index(['tenant_id', 'tenant_user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
