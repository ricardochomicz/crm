<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommercialOrdersChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $tenantId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.' . $this->tenantId . '.orders')];
    }

    public function broadcastAs(): string
    {
        return 'orders.changed';
    }

    public function broadcastWith(): array
    {
        // Never broadcast client data or order identifiers to the whole tenant.
        return ['refresh' => true];
    }
}
