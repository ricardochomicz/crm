<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->foreignUuid('order_status_id')->nullable()
                ->constrained('order_statuses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('order_status_id');
        });
    }
};
