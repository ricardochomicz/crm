<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('contact_pending')->default(false);
        });
    }
    public function down(): void {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('contact_pending'));
    }
};
