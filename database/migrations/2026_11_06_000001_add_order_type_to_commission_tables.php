<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('commission_tables', function (Blueprint $table) {
            $table->string('order_type', 64)->nullable();
        });
    }
    public function down(): void {
        Schema::table('commission_tables', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};
