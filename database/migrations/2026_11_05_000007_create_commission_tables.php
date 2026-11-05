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
 }
 public function down(): void { Schema::dropIfExists('tenant_commission_settings'); }
};
