<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('commercial_goals', function (Blueprint $table): void {
            $table->string('target_scope', 20)->default('COMPANY');
            $table->uuid('responsible_membership_id')->nullable()->index();
            $table->foreign('responsible_membership_id')->references('id')->on('tenant_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commercial_goals', function (Blueprint $table): void {
            $table->dropForeign(['responsible_membership_id']);
            $table->dropIndex(['responsible_membership_id']);
            $table->dropColumn(['target_scope', 'responsible_membership_id']);
        });
    }
};
