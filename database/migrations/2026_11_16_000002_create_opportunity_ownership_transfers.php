<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_ownership_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('client_id')->index();
            $table->uuid('opportunity_id')->index();
            $table->uuid('from_tenant_user_id');
            $table->uuid('to_tenant_user_id');
            $table->unsignedBigInteger('actor_user_id');
            $table->string('source_type', 32);
            $table->uuid('source_id');
            $table->text('reason');
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'opportunity_id'], 'opportunity_transfer_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_ownership_transfers');
    }
};
