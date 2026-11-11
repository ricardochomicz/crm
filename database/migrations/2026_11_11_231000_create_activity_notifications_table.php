<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('tenant_user_id');
            $table->uuid('calendar_event_id');
            $table->string('kind', 32);
            $table->string('title', 220);
            $table->string('message', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'tenant_user_id', 'calendar_event_id', 'kind'], 'activity_notifications_unique');
            $table->index(['tenant_id', 'tenant_user_id', 'read_at'], 'activity_notifications_inbox');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_notifications');
    }
};
