<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portfolio_redistributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('source_membership_id')->constrained('tenant_users')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('mode', 20);
            $table->text('reason');
            $table->unsignedInteger('client_count');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('client_portfolio_redistribution_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('redistribution_id')
                ->constrained('client_portfolio_redistributions')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('destination_membership_id')->constrained('tenant_users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['redistribution_id', 'client_id'], 'portfolio_redistribution_unique_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portfolio_redistribution_items');
        Schema::dropIfExists('client_portfolio_redistributions');
    }
};
