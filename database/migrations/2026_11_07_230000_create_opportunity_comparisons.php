<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opportunity_comparisons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('public_token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'opportunity_id']);
        });
        Schema::create('opportunity_comparison_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('comparison_id')->constrained('opportunity_comparisons')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('operator_name');
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->decimal('monthly_unit_amount', 12, 2);
            $table->decimal('one_time_unit_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_comparison_options');
        Schema::dropIfExists('opportunity_comparisons');
    }
};
