<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasOpportunityForeignKey(): bool
    {
        return \Illuminate\Support\Facades\DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
            ->where('TABLE_NAME', 'calendar_events')
            ->where('COLUMN_NAME', 'opportunity_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    private function hasOpportunityIndex(): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM calendar_events'))
            ->contains(fn (object $index): bool => $index->Key_name === 'calendar_events_tenant_opportunity_idx');
    }

    public function up(): void
    {
        if (! Schema::hasColumn('calendar_events', 'opportunity_id')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->foreignUuid('opportunity_id')->nullable()->after('client_id');
            });
        }

        if (! $this->hasOpportunityForeignKey()) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->foreign('opportunity_id', 'calendar_events_opportunity_fk')
                    ->references('id')->on('opportunities')->restrictOnDelete();
            });
        }

        if (! $this->hasOpportunityIndex()) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->index(['tenant_id', 'opportunity_id'], 'calendar_events_tenant_opportunity_idx');
            });
        }
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
