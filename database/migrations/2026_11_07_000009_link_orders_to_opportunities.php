<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table) {
            $table->foreignUuid('opportunity_id')->nullable()->unique()->constrained('opportunities')->restrictOnDelete();
        });

        Schema::create('commercial_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commercial_order_id')->constrained('commercial_orders')->cascadeOnDelete();
            $table->foreignUuid('opportunity_item_id')->nullable()->constrained('opportunity_items')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->restrictOnDelete();
            $table->foreignUuid('order_type_id')->nullable()->constrained('order_types')->restrictOnDelete();
            $table->string('line_number', 20)->nullable();
            $table->string('description', 200);
            $table->unsignedInteger('quantity');
            $table->decimal('monthly_unit_amount', 14, 2);
            $table->decimal('one_time_unit_amount', 14, 2)->default(0);
            $table->string('status', 24)->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('opportunity_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_order_items');
        Schema::table('commercial_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('opportunity_id'));
    }
};
