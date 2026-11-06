<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('commercial_participants', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
   $table->string('role', 32);
   $table->string('person_type', 2);
   $table->string('name', 200);
   $table->string('document', 14)->nullable();
   $table->string('email')->nullable();
   $table->string('phone', 30)->nullable();
   $table->boolean('is_active')->default(true);
   $table->text('notes')->nullable();
   $table->timestamps();
   $table->unique(['tenant_id', 'document']);
   $table->index(['tenant_id', 'role', 'is_active']);
  });
  Schema::create('commercial_participant_commissions', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
   $table->foreignUuid('commercial_participant_id')->constrained('commercial_participants')->cascadeOnDelete();
   $table->foreignId('operator_id')->constrained('operators');
   $table->string('order_type', 64);
   $table->decimal('percentage', 7, 2);
   $table->date('starts_on');
   $table->date('ends_on')->nullable();
   $table->boolean('is_active')->default(true);
   $table->timestamps();
   $table->index(['tenant_id', 'commercial_participant_id', 'operator_id', 'order_type'], 'participant_commission_lookup');
  });
 }
 public function down(): void {
  Schema::dropIfExists('commercial_participant_commissions');
  Schema::dropIfExists('commercial_participants');
 }
};
