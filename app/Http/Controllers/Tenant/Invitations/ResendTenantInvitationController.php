<?php

namespace App\Http\Controllers\Tenant\Invitations;

use App\Actions\Tenant\ResendTenantInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ResendTenantInvitationRequest;
use App\Models\Tenant;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Throwable;

class ResendTenantInvitationController extends Controller
{
    public function __invoke(
        ResendTenantInvitationRequest $request,
        ResendTenantInvitationAction $action,
    ): JsonResponse {
        $frontendUrl = config('app.frontend_url');

        if (
            ! is_string($frontendUrl)
            || filter_var($frontendUrl, FILTER_VALIDATE_URL) === false
            || ! in_array(
                parse_url($frontendUrl, PHP_URL_SCHEME),
                ['http', 'https'],
                true,
            )
            || parse_url($frontendUrl, PHP_URL_QUERY) !== null
            || parse_url($frontendUrl, PHP_URL_FRAGMENT) !== null
        ) {
            throw new LogicException(
                'Configure uma URL válida para o frontend.',
            );
        }

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('active_tenant');

        $result = $action->handle(
            $request->user(),
            $tenant,
            (string) $request->route('invitationId'),
        );

        $invitation = $result['invitation'];

        $invitationUrl = rtrim($frontendUrl, '/')
            . '/convites#token=' . rawurlencode($result['token']);

        $notificationDispatched = false;

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new TenantInvitationNotification(
                    tenantName: $tenant->name,
                    invitationUrl: $invitationUrl,
                    expiresAtLabel: $invitation->expires_at
                        ->copy()
                        ->utc()
                        ->format('d/m/Y H:i \U\T\C'),
                ));

            $notificationDispatched = true;
        } catch (Throwable $exception) {
            Log::warning('Falha ao encaminhar reenvio de convite.', [
                'tenant_id' => $tenant->getKey(),
                'invitation_id' => $invitation->getKey(),
                'exception_class' => $exception::class,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $invitation->getKey(),
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'status' => $invitation->status->value,
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
            'meta' => [
                'notification_dispatched' => $notificationDispatched,
            ],
        ]);
    }
}
