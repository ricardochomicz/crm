<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_commission_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators');
            $table->string('order_type', 64);
            $table->string('commercial_role', 32);
            $table->decimal('percentage', 7, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'operator_id', 'order_type', 'commercial_role'], 'team_commission_lookup');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('team_commission_rules');
    }
};
