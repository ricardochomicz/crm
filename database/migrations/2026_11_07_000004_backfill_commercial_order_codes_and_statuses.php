<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('commercial_orders')->orderBy('id')->chunk(100, function ($orders): void {
            foreach ($orders as $order) {
                $updates = [];

                if (blank($order->code)) {
                    $date = \Carbon\Carbon::parse($order->created_at ?? now())->format('YmdHis');
                    $updates['code'] = 'PED-' . $date . '-' . Str::upper(Str::random(8));
                }

                if (! $order->order_status_id) {
                    $initial = DB::table('order_statuses')
                        ->where('tenant_id', $order->tenant_id)
                        ->where('is_initial', true)
                        ->value('id');

                    if ($initial) {
                        $updates['order_status_id'] = $initial;
                    }
                }

                if ($updates !== []) {
                    DB::table('commercial_orders')->where('id', $order->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        // Não reverte identificadores comerciais ou status já atribuídos.
    }
};
