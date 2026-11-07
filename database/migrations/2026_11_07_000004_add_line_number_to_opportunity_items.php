<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->string('line_number', 20)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', fn (Blueprint $table) => $table->dropColumn('line_number'));
    }
};
