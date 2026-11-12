<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_priority_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('tenant_user_id');
            $table->uuid('opportunity_id');
            $table->date('acknowledged_on');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'tenant_user_id', 'opportunity_id', 'acknowledged_on'],
                'commercial_priority_ack_unique'
            );
            $table->index(['tenant_id', 'tenant_user_id', 'acknowledged_on'], 'commercial_priority_ack_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_priority_acknowledgements');
    }
};
