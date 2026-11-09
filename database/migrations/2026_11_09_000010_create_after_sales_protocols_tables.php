<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('after_sales_protocols', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 40);
            $table->uuid('client_id')->index();
            $table->uuid('commercial_order_id')->nullable()->index();
            $table->uuid('opened_by_tenant_user_id')->index();
            $table->uuid('assigned_to_tenant_user_id')->nullable()->index();
            $table->string('title', 180);
            $table->string('category', 40);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->text('description');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'due_at']);
        });
        Schema::create('after_sales_protocol_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('protocol_id')->index();
            $table->uuid('tenant_user_id');
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('after_sales_protocol_history');
        Schema::dropIfExists('after_sales_protocols');
    }
};
