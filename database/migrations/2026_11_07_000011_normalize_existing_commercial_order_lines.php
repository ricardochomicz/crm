<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('commercial_order_items')->where('quantity', '>', 1)
            ->orderBy('id')->chunk(100, function ($items): void {
                foreach ($items as $item) {
                    DB::transaction(function () use ($item): void {
                        $current = DB::table('commercial_order_items')
                            ->where('id', $item->id)->lockForUpdate()->first();

                        if (! $current || $current->quantity <= 1) {
                            return;
                        }

                        $quantity = (int) $current->quantity;
                        DB::table('commercial_order_items')->where('id', $current->id)
                            ->update(['quantity' => 1]);

                        for ($index = 2; $index <= $quantity; $index++) {
                            $copy = (array) $current;
                            $copy['id'] = (string) Str::uuid();
                            $copy['opportunity_item_id'] = null;
                            $copy['quantity'] = 1;
                            $copy['created_at'] = now();
                            $copy['updated_at'] = now();
                            DB::table('commercial_order_items')->insert($copy);
                        }
                    });
                }
            });
    }

    public function down(): void
    {
        // Não é seguro reagrupar linhas: elas podem ter datas e status diferentes.
    }
};
