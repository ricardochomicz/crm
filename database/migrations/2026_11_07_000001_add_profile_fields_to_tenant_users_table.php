<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->text('cpf')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('mobile_phone', 30)->nullable();
            $table->string('secondary_phone', 30)->nullable();
            $table->date('hired_at')->nullable();
            $table->string('employee_code', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->dropColumn(['cpf', 'birth_date', 'mobile_phone', 'secondary_phone', 'hired_at', 'employee_code']);
        });
    }
};
