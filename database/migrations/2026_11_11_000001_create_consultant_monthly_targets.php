<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consultant_monthly_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->date('month');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['tenant_id', 'tenant_user_id', 'month'], 'consultant_monthly_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_monthly_targets');
    }
};
