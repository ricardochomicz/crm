<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table): void {
            $table->string('cpf', 11)->nullable();
            $table->date('birth_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_contacts', function (Blueprint $table): void {
            $table->dropColumn(['cpf', 'birth_date']);
        });
    }
};
