<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->uuid('supervisor_membership_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('teams')->whereNull('supervisor_membership_id')->exists()) {
            throw new RuntimeException('Existem equipes sem supervisor; atribua supervisores antes do rollback.');
        }
        Schema::table('teams', function (Blueprint $table): void {
            $table->uuid('supervisor_membership_id')->nullable(false)->change();
        });
    }
};
