<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreignUuid('opportunity_id')->nullable()->after('client_id');
            $table->foreign('opportunity_id', 'calendar_events_opportunity_fk')
                ->references('id')->on('opportunities')->restrictOnDelete();
            $table->index(['tenant_id', 'opportunity_id'], 'calendar_events_tenant_opportunity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropIndex('calendar_events_tenant_opportunity_idx');
            $table->dropForeign('calendar_events_opportunity_fk');
            $table->dropColumn('opportunity_id');
        });
    }
};
