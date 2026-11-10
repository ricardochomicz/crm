<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_goals', function (Blueprint $table): void {
            $table->string('category', 80)->nullable()->after('order_type');
            $table->json('negotiation_type_ids')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_goals', fn (Blueprint $table) => $table->dropColumn(['category', 'negotiation_type_ids']));
    }
};
