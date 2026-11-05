<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_operators', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('operators')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'operator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_operators');
        Schema::dropIfExists('operators');
    }
};
