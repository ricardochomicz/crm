<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('operator_classifications',function(Blueprint $t){
   $t->id(); $t->foreignId('operator_id')->constrained(); $t->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
   $t->string('code',30); $t->string('name',100); $t->string('description',255)->nullable(); $t->boolean('is_active')->default(true); $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
   $t->index(['operator_id','tenant_id','code']);
  });
  Schema::create('classification_renewal_rules',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignId('classification_id')->constrained('operator_classifications')->restrictOnDelete();
   $t->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete(); $t->unsignedSmallInteger('minimum_months');
   $t->date('valid_from'); $t->date('valid_until')->nullable(); $t->boolean('is_active')->default(true); $t->string('notes',500)->nullable(); $t->timestamps();
   $t->index(['classification_id','tenant_id','valid_from'],'renewal_rule_lookup_idx');
  });
  Schema::create('clients',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
   $t->string('person_type',2); $t->string('document',14); $t->string('company_name',255);
   $t->string('phone',20)->nullable(); $t->string('email')->nullable(); $t->string('zip_code',8)->nullable();
   $t->string('address')->nullable(); $t->string('address_number',20)->nullable(); $t->string('address_complement')->nullable();
   $t->string('neighborhood',150)->nullable(); $t->string('city',150)->nullable(); $t->char('state',2)->nullable();
   $t->foreignId('operator_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('classification_id')->nullable()->constrained('operator_classifications')->nullOnDelete();
   $t->date('contract_started_on')->nullable(); $t->date('contract_ends_on')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
   $t->unique(['tenant_id','document']); $t->index(['tenant_id','company_name']);
  });
  Schema::create('client_contacts',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('client_id')->constrained()->cascadeOnDelete();
   $t->string('name',200); $t->string('position',100)->nullable(); $t->string('phone',20)->nullable(); $t->string('email')->nullable();
   $t->boolean('is_primary')->default(false); $t->boolean('is_active')->default(true); $t->text('notes')->nullable(); $t->timestamps();
  });
  Schema::create('client_sellers',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('client_id')->constrained()->cascadeOnDelete();
   $t->foreignUuid('tenant_user_id'); $t->foreign('tenant_user_id','client_sellers_membership_fk')->references('id')->on('tenant_users')->restrictOnDelete();
   $t->foreignId('assigned_by')->nullable(); $t->foreign('assigned_by','client_sellers_assigned_by_fk')->references('id')->on('users')->nullOnDelete();
   $t->timestamp('started_at'); $t->timestamp('ended_at')->nullable(); $t->string('reason')->nullable(); $t->timestamps();
   $t->index(['tenant_id','client_id','ended_at']);
  });
  Schema::create('client_operator_history',function(Blueprint $t){
   $t->uuid('id')->primary(); $t->foreignUuid('tenant_id')->constrained()->cascadeOnDelete(); $t->foreignUuid('client_id')->constrained()->cascadeOnDelete();
   $t->foreignId('operator_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('classification_id')->nullable()->constrained('operator_classifications')->nullOnDelete();
   $t->timestamp('changed_at'); $t->foreignId('changed_by')->nullable(); $t->foreign('changed_by','client_operator_changed_by_fk')->references('id')->on('users')->nullOnDelete();
   $t->timestamps();
  });
 }
 public function down(): void {
  foreach(['client_operator_history','client_sellers','client_contacts','clients','classification_renewal_rules','operator_classifications'] as $table) Schema::dropIfExists($table);
 }
};
