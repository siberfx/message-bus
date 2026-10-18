<?php

namespace Siberfx\MessageBus\Contracts;

/**
 * Pushes events to realtime channels (Reverb, Pusher, Ably, webhooks, ...).
 */
interface RealtimeDriver
{
    /**
     * @param  list<string>  $channels  Channel names, "private-" / "presence-" prefixed when authorized.
     * @param  array<string, mixed>  $payload
     */
    public function publish(array $channels, string $event, array $payload = []): void;
}
