<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('client_operator_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained()->restrictOnDelete();
            $table->foreignId('classification_id')->constrained('operator_classifications')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'client_id', 'operator_id'], 'client_operator_classifications_unique');
        });
        // Preserve classifications previously assigned directly to clients.
        DB::table('clients')->whereNotNull('operator_id')->whereNotNull('classification_id')
            ->orderBy('id')->chunk(200, function ($clients) {
                foreach ($clients as $client) {
                    $valid = DB::table('operator_classifications')
                        ->where('id', $client->classification_id)
                        ->where('operator_id', $client->operator_id)
                        ->where(function ($q) use ($client) { $q->whereNull('tenant_id')->orWhere('tenant_id', $client->tenant_id); })
                        ->exists();
                    if ($valid) DB::table('client_operator_classifications')->insert([
                        'tenant_id' => $client->tenant_id, 'client_id' => $client->id,
                        'operator_id' => $client->operator_id, 'classification_id' => $client->classification_id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }
    public function down(): void { Schema::dropIfExists('client_operator_classifications'); }
};
