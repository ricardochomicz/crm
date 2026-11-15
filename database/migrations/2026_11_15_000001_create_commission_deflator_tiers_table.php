<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_deflator_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators');
            $table->string('order_type', 64);
            $table->decimal('reduction_from_percentage', 7, 4);
            $table->decimal('reduction_to_percentage', 7, 4)->nullable();
            $table->decimal('commission_deflator_percentage', 7, 4);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'operator_id', 'order_type', 'is_active'], 'commission_deflator_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_deflator_tiers');
    }
};
