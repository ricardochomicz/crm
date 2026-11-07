<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users');
            $table->string('commentable_type', 40);
            $table->uuid('commentable_id');
            $table->text('content');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'commentable_type', 'commentable_id', 'created_at'], 'comments_timeline_index');
        });
        Schema::create('attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users');
            $table->foreignUuid('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comments');
    }
};
