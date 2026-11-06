<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('commercial_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained();
            $table->string('order_type', 64);
            $table->string('commercial_role', 32);
            $table->string('metric', 16);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'operator_id', 'order_type', 'commercial_role'], 'commercial_goals_lookup_idx');
        });
        Schema::create('commercial_goal_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('commercial_goal_id');
            $table->foreign('commercial_goal_id', 'goal_tiers_goal_fk')->references('id')->on('commercial_goals')->cascadeOnDelete();
            $table->decimal('minimum', 14, 2);
            $table->decimal('percentage', 7, 2);
            $table->timestamps();
            $table->unique(['commercial_goal_id', 'minimum'], 'goal_tiers_unique_minimum');
        });
    }
    public function down(): void {
        Schema::dropIfExists('commercial_goal_tiers');
        Schema::dropIfExists('commercial_goals');
    }
};
