<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('name');
            $table->enum('type', ['PF', 'PJ']);
            $table->string('document');
            $table->string('trade_name')->nullable(); // Nome fantasia
            $table->string('email', 254);
            $table->string('phone');
                $table->enum('status', ['ACTIVE', 'SUSPENDED', 'CANCELLED'])
        ->default('ACTIVE');

    $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
