<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_portal_accesses', function (Blueprint $table): void {
            $table->string('request_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('customer_portal_accesses', function (Blueprint $table): void {
            $table->dropIndex(['request_hash']);
            $table->dropColumn('request_hash');
        });
    }
};
