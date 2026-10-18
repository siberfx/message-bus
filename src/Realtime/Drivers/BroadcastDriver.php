<?php

namespace Siberfx\MessageBus\Realtime\Drivers;

use Illuminate\Contracts\Broadcasting\Factory;
use Siberfx\MessageBus\Contracts\RealtimeDriver;

/**
 * Publishes through a Laravel broadcasting connection: Reverb, Pusher,
 * Ably, Redis (Soketi / laravel-echo-server), log or null.
 *
 * Channel names keep Laravel's convention: "private-orders.1",
 * "presence-room.1" or a plain public name. Clients listen with Echo.
 */
class BroadcastDriver implements RealtimeDriver
{
    public function __construct(
        protected Factory $broadcast,
        protected ?string $connection = null,
    ) {
    }

    public function publish(array $channels, string $event, array $payload = []): void
    {
        $this->broadcast->connection($this->connection)->broadcast($channels, $event, $payload);
    }
}
