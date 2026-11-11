<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->boolean('portal_access_enabled')->default(false);
        });
        Schema::create('customer_portal_accesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_contact_id')->constrained()->cascadeOnDelete();
            $table->string('challenge_hash', 64)->unique();
            $table->string('request_hash', 64)->index();
            $table->string('code_hash');
            $table->timestamp('code_expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('session_hash', 64)->nullable()->unique();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_portal_accesses');
        Schema::table('client_contacts', fn (Blueprint $table) => $table->dropColumn('portal_access_enabled'));
    }
};
