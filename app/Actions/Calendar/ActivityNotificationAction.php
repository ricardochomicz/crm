<?php

namespace App\Actions\Calendar;

use App\Events\ActivityNotificationChanged;
use App\Models\CalendarEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityNotificationAction
{
    public function notify(CalendarEvent $event, string $kind): void
    {
        if (! in_array($kind, ['assigned', 'due_soon', 'overdue'], true) || $event->status !== 'pending' || ! in_array($event->type, ['task', 'reminder'], true)) return;

        $member = DB::table('tenant_users')->where('tenant_id', $event->tenant_id)
            ->where('id', $event->tenant_user_id)->where('status', 'ACTIVE')->first();
        if (! $member) return;

        $labels = [
            'assigned' => ['Nova atividade atribuída', 'Você recebeu a atividade: '],
            'due_soon' => ['Atividade próxima do vencimento', 'Vence nas próximas 24 horas: '],
            'overdue' => ['Atividade atrasada', 'Prazo ultrapassado: '],
        ];
        [$title, $prefix] = $labels[$kind];
        $inserted = DB::table('activity_notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $event->tenant_id,
            'tenant_user_id' => $event->tenant_user_id,
            'calendar_event_id' => $event->id,
            'kind' => $kind,
            'title' => $title,
            'message' => $prefix.$event->title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted) DB::afterCommit(fn () => ActivityNotificationChanged::dispatch($event->tenant_id, $event->tenant_user_id));
    }
}
