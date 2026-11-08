<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators');
            $table->foreignId('classification_id')->nullable()->constrained('operator_classifications')->nullOnDelete();
            $table->string('renewal_policy', 32)->default('PENDING');
            $table->date('fixed_renewal_date')->nullable();
            $table->string('status', 24)->default('ACTIVE');
            $table->timestamps();
            $table->index(['tenant_id', 'client_id', 'operator_id']);
        });

        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->foreignUuid('client_contract_id')->nullable()
                ->constrained('client_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('client_contract_id'));
        Schema::dropIfExists('client_contracts');
    }
};
