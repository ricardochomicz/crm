<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opportunity_funnel_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'position']);
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('funnel_stage_id')->nullable()->constrained('opportunity_funnel_stages')->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->restrictOnDelete();
            $table->foreignUuid('parent_opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->string('external_protocol', 100)->nullable();
            $table->string('order_type', 40);
            $table->string('status', 24)->default('open');
            $table->text('description')->nullable();
            $table->date('expected_close_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('estimated_monthly_amount', 14, 2)->default(0);
            $table->decimal('estimated_one_time_amount', 14, 2)->default(0);
            $table->unsignedTinyInteger('probability')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('lost_reason', 200)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'expected_close_at']);
            $table->index(['tenant_id', 'tenant_user_id', 'status']);
        });

        Schema::create('opportunity_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->restrictOnDelete();
            $table->foreignId('origin_operator_id')->nullable()->constrained('operators')->restrictOnDelete();
            $table->string('description', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('monthly_unit_amount', 14, 2)->default(0);
            $table->decimal('one_time_unit_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('opportunity_id');
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreignUuid('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('opportunity_id'));
        Schema::dropIfExists('opportunity_items');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('opportunity_funnel_stages');
    }
};
