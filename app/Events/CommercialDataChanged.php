<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Tenant-scoped invalidation only: never expose opportunity or activity data. */
class CommercialDataChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $tenantId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.' . $this->tenantId . '.commercial')];
    }

    public function broadcastAs(): string
    {
        return 'commercial.changed';
    }

    public function broadcastWith(): array
    {
        return ['refresh' => true];
    }
}
