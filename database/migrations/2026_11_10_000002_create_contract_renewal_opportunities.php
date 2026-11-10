<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contract_renewal_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_order_id')->constrained('commercial_orders')->restrictOnDelete();
            $table->foreignUuid('opportunity_id')->constrained('opportunities')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'source_order_id', 'opportunity_id'], 'renewal_origin_opportunity_unique');
            $table->index(['tenant_id', 'source_order_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('contract_renewal_opportunities'); }
};
