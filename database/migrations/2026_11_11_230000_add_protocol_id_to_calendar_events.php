<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreignUuid('protocol_id')->nullable()->after('opportunity_id');
            $table->index(['tenant_id', 'protocol_id']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'protocol_id']);
            $table->dropColumn('protocol_id');
        });
    }
};
