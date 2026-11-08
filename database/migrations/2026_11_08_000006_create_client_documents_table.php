<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignUuid('client_id')->index();
            $table->foreignUuid('tenant_user_id')->nullable();
            $table->string('category', 40);
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->index(['tenant_id', 'client_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('client_documents'); }
};
