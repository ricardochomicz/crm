<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignUuid('order_type_id')->nullable()->constrained('order_types')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('opportunities', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_type_id'));
    }
};
