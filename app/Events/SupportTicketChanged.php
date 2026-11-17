<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SupportTicketChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $ticketId,
        public readonly string $tenantId,
        public readonly int $customerUserId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('platform.support'),
            new PrivateChannel('tenant.'.$this->tenantId.'.user.'.$this->customerUserId.'.support'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.ticket.changed';
    }

    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticketId];
    }
}
