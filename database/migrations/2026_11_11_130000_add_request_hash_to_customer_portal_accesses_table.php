<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_portal_accesses', 'request_hash')) {
            Schema::table('customer_portal_accesses', function (Blueprint $table): void {
                $table->string('request_hash', 64)->nullable();
            });
        }

        if (! Schema::hasIndex('customer_portal_accesses', ['request_hash'])) {
            Schema::table('customer_portal_accesses', function (Blueprint $table): void {
                $table->index('request_hash');
            });
        }
    }

    public function down(): void
    {
        // A coluna também pertence à migration de criação; o rollback desta
        // compatibilidade deve preservá-la. A migration inicial remove a tabela.
    }
};
