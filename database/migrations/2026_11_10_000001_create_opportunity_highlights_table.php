<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opportunity_highlights', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->uuid('tenant_user_id');
            $table->uuid('opportunity_id');
            $table->timestamps();
            $table->primary(['tenant_id', 'tenant_user_id', 'opportunity_id'], 'opportunity_highlights_pk');
            $table->index(['tenant_id', 'opportunity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_highlights');
    }
};
