<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_forecast_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users')->restrictOnDelete();
            $table->date('previous_date')->nullable();
            $table->date('new_date');
            $table->foreignUuid('previous_stage_id')->nullable()->constrained('opportunity_funnel_stages')->nullOnDelete();
            $table->foreignUuid('new_stage_id')->nullable()->constrained('opportunity_funnel_stages')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'opportunity_id', 'created_at'], 'forecast_changes_tenant_opportunity_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_forecast_changes');
    }
};
