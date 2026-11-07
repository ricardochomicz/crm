<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->string('operation_type', 24)->default('new')->after('line_number');
        });
        DB::table('opportunity_items')->whereNotNull('line_number')->update(['operation_type' => 'portability']);
    }

    public function down(): void
    {
        Schema::table('opportunity_items', fn (Blueprint $table) => $table->dropColumn('operation_type'));
    }
};
