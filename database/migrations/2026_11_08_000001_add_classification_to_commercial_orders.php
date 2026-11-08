<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->foreignId('classification_id')
                ->nullable()
                ->after('operator_id')
                ->constrained('operator_classifications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('classification_id');
        });
    }
};
