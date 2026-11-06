<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commercial_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->string('order_type', 64);
            $table->string('customer_name', 200);
            $table->string('customer_document', 14)->nullable();
            $table->decimal('monthly_amount', 14, 2);
            $table->unsignedInteger('line_quantity');
            $table->string('status', 24)->default('DRAFT');
            $table->date('sold_on');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sold_on']);
        });
        Schema::create('commercial_order_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('commercial_order_id');
            $table->foreign('commercial_order_id', 'order_participants_order_fk')->references('id')->on('commercial_orders')->cascadeOnDelete();
            $table->string('role', 32);
            $table->foreignUuid('tenant_user_id')->nullable();
            $table->foreign('tenant_user_id', 'order_participants_member_fk')->references('id')->on('tenant_users')->restrictOnDelete();
            $table->foreignUuid('commercial_participant_id')->nullable();
            $table->foreign('commercial_participant_id', 'order_participants_external_fk')->references('id')->on('commercial_participants')->restrictOnDelete();
            $table->string('participant_name', 200);
            $table->timestamps();
            $table->unique(['commercial_order_id', 'role'], 'order_participants_unique_role');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('commercial_order_participants');
        Schema::dropIfExists('commercial_orders');
    }
};
