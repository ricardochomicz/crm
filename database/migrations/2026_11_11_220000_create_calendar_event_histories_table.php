<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_event_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->foreignUuid('actor_tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('action', 32);
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'calendar_event_id', 'created_at'], 'calendar_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_histories');
    }
};
