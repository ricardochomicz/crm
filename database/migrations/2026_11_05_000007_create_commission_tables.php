<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('tenant_commission_settings', function (Blueprint $t) {
   $t->uuid('tenant_id')->primary();
   $t->decimal('default_deduction_percentage',5,2)->default(0);
   $t->timestamps();
   $t->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
  });
  Schema::create('commission_tables', function (Blueprint $t) {
   $t->uuid('id')->primary();
   $t->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
   $t->foreignId('operator_id')->constrained('operators')->restrictOnDelete();
   $t->string('name',180);
   $t->decimal('percentage',8,2);
   $t->decimal('deduction_percentage',5,2)->nullable();
   $t->boolean('is_active')->default(true);
   $t->text('description')->nullable();
   $t->timestamps();
   $t->index(['tenant_id','operator_id']);
  });
 }
 public function down(): void { Schema::dropIfExists('commission_tables'); Schema::dropIfExists('tenant_commission_settings'); }
};
