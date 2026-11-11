<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use App\Models\CalendarEvent;
use App\Actions\Calendar\ActivityNotificationAction;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'platform:admin {userId : ID do usuário} {--revoke : Revogar acesso administrativo}',
    function (): int {
        $userId = (string) $this->argument('userId');

        if (! ctype_digit($userId) || (int) $userId < 1) {
            $this->error('Informe um ID de usuário válido.');

            return 1;
        }

        $enabled = ! $this->option('revoke');

        $user = DB::transaction(function () use ($userId, $enabled): ?User {
            $user = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return null;
            }

            $user->is_platform_admin = $enabled;
            $user->save();

            return $user;
        }, attempts: 5);

        if ($user === null) {
            $this->error('Usuário não encontrado.');

            return 1;
        }

        Log::notice('Privilégio administrativo da plataforma definido via CLI.', [
            'target_user_id' => $user->getKey(),
            'is_platform_admin' => $enabled,
            'source' => 'artisan',
        ]);

        $this->info(
            $enabled
                ? "Acesso administrativo concedido ao usuário {$user->getKey()}."
                : "Acesso administrativo revogado do usuário {$user->getKey()}.",
        );

        return 0;
    },
)->purpose('Conceder ou revogar acesso administrativo da plataforma');

Artisan::command('activities:notify-due', function (ActivityNotificationAction $notifications): void {
    CalendarEvent::query()->whereIn('type', ['task', 'reminder'])
        ->where('status', 'pending')->where('ends_at', '<=', now()->addDay())
        ->orderBy('id')->chunkById(200, function ($events) use ($notifications): void {
            foreach ($events as $event) {
                $notifications->notify($event, $event->ends_at->isPast() ? 'overdue' : 'due_soon');
            }
        });
})->purpose('Notificar tarefas próximas do vencimento ou atrasadas');

Schedule::command('activities:notify-due')->everyFiveMinutes()->withoutOverlapping();
