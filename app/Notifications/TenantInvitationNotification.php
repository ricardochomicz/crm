<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;

class TenantInvitationNotification extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $tenantName,
        public string $invitationUrl,
        public string $expiresAtLabel
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Convite para participar de uma empresa')
            ->greeting('Olá!')
            ->line("Você recebeu um convite para participar da empresa {$this->tenantName}.")
            ->line("Este convite é válido até {$this->expiresAtLabel}.")
            ->action('Visualizar convite', $this->invitationUrl)
            ->line('Para aceitar, entre ou cadastre-se usando o mesmo e-mail que recebeu este convite.')
            ->line('Se você não reconhece este convite, pode ignorar esta mensagem.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
