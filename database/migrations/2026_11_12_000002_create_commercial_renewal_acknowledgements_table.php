<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_renewal_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->foreignUuid('client_contract_id')->constrained('client_contracts')->cascadeOnDelete();
            $table->date('acknowledged_on');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'tenant_user_id', 'client_contract_id', 'acknowledged_on'],
                'commercial_renewal_ack_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_renewal_acknowledgements');
    }
};
