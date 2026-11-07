<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_order_items', function (Blueprint $table): void {
            $table->date('activated_at')->nullable();
            $table->index(['commercial_order_id', 'status']);
        });

        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->timestamp('administratively_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->dropColumn('administratively_completed_at');
        });

        Schema::table('commercial_order_items', function (Blueprint $table): void {
            $table->dropIndex(['commercial_order_id', 'status']);
            $table->dropColumn('activated_at');
        });
    }
};
