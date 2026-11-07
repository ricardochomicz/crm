<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table): void {
            $table->boolean('is_initial')->default(false);
        });

        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique();
            $table->foreignUuid('tenant_user_id')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->date('expected_activation_at')->nullable();
            $table->date('activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_user_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['code', 'expected_activation_at', 'activated_at']);
        });
        Schema::table('order_statuses', fn (Blueprint $table) => $table->dropColumn('is_initial'));
    }
};
