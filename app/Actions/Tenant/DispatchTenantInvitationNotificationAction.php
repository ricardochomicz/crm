<?php

namespace App\Actions\Tenant;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Throwable;

class DispatchTenantInvitationNotificationAction
{
    public function execute(Tenant $tenant, TenantInvitation $invitation, string $token): bool
    {
        $frontendUrl = config('app.frontend_url');

        if (
            ! is_string($frontendUrl)
            || filter_var($frontendUrl, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($frontendUrl, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($frontendUrl, PHP_URL_QUERY) !== null
            || parse_url($frontendUrl, PHP_URL_FRAGMENT) !== null
        ) {
            throw new LogicException('Configure uma URL válida para o frontend.');
        }

        $invitationUrl = rtrim($frontendUrl, '/')
            . '/convites#token=' . rawurlencode($token);

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new TenantInvitationNotification(
                    tenantName: $tenant->name,
                    invitationUrl: $invitationUrl,
                    expiresAtLabel: $invitation->expires_at
                        ->copy()->utc()->format('d/m/Y H:i \U\T\C'),
                ));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Falha ao encaminhar notificação de convite.', [
                'tenant_id' => $tenant->getKey(),
                'invitation_id' => $invitation->getKey(),
                'exception_class' => $exception::class,
            ]);

            return false;
        }
    }
}
