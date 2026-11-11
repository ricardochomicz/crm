<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_commission_accruals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('commercial_order_id')->constrained()->restrictOnDelete();
            $table->uuid('source_item_id');
            $table->unsignedInteger('revision');
            $table->boolean('is_current')->default(true);
            $table->string('status', 24);
            $table->string('source', 24);
            $table->foreignUuid('captured_by')->constrained('tenant_users')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->date('sold_on');
            $table->date('activated_on')->nullable();
            $table->foreignId('operator_id')->constrained()->restrictOnDelete();
            $table->string('order_type', 64)->nullable();
            $table->string('line_number', 20)->nullable();
            $table->string('description', 200);
            $table->unsignedInteger('quantity');
            $table->decimal('base_amount', 22, 2);
            $table->decimal('gross_amount', 24, 2)->nullable();
            $table->decimal('deduction_amount', 24, 2)->nullable();
            $table->decimal('net_amount', 24, 2)->nullable();
            $table->decimal('participant_total', 24, 2)->nullable();
            $table->decimal('company_balance', 24, 2)->nullable();
            $table->json('rule_snapshot');
            $table->json('participant_amounts');
            $table->json('issues');
            $table->timestamps();
            $table->unique(['commercial_order_id', 'revision', 'source_item_id'], 'commission_accrual_revision_unique');
            $table->index(['tenant_id', 'commercial_order_id', 'is_current'], 'commission_accrual_order_lookup');
            $table->index(['tenant_id', 'is_current', 'status', 'activated_on'], 'commission_accrual_eligibility_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_commission_accruals');
    }
};
