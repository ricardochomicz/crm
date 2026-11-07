<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150)->unique();
            $table->boolean('requires_number')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->foreignUuid('order_type_id')->nullable()->constrained('order_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_type_id'));
        Schema::dropIfExists('order_types');
    }
};
