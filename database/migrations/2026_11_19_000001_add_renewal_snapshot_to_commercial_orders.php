<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            // Null means legacy/unresolved: never silently infer an original term.
            $table->foreignUuid('renewal_rule_id')->nullable()->after('classification_id');
            $table->unsignedSmallInteger('renewal_months_snapshot')->nullable()->after('renewal_rule_id');
            $table->date('renewal_date_snapshot')->nullable()->after('renewal_months_snapshot');
            $table->timestamp('renewal_snapshot_at')->nullable()->after('renewal_date_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'renewal_rule_id',
                'renewal_months_snapshot',
                'renewal_date_snapshot',
                'renewal_snapshot_at',
            ]);
        });
    }
};
