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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->unique()
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignUuid('subscription_plan_id')
                ->constrained('subscription_plans')
                ->restrictOnDelete();

            $table->enum('status', [
                'PENDING',
                'TRIALING',
                'ACTIVE',
                'PAST_DUE',
                'CANCELLED',
            ])->default('PENDING');

            $table->decimal('monthly_price', 12, 2);
            $table->char('currency', 3)->default('BRL');
            $table->unsignedInteger('user_limit');
            $table->unsignedInteger('storage_limit_mb');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();

            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
