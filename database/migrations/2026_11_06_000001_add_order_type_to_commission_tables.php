<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('commission_tables', function (Blueprint $table) {
            $table->string('order_type', 64)->nullable();
            $table->unique(['tenant_id', 'operator_id', 'order_type'], 'commission_tenant_operator_type_unique');
        });
    }
    public function down(): void {
        Schema::table('commission_tables', function (Blueprint $table) {
            $table->dropUnique('commission_tenant_operator_type_unique');
            $table->dropColumn('order_type');
        });
    }
};
