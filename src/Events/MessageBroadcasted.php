<?php

namespace Siberfx\MessageBus\Events;

use Siberfx\MessageBus\Envelope;

/**
 * Fired after a message was pushed to realtime channels.
 */
class MessageBroadcasted
{
    /**
     * @param  list<string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Envelope $envelope,
        public readonly string $driver,
        public readonly array $channels,
        public readonly string $event,
        public readonly array $payload,
    ) {
    }
}
