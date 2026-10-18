<?php

namespace Siberfx\MessageBus\Events;

use Siberfx\MessageBus\Envelope;

/**
 * Fired after a message was sent to a transport.
 */
class MessageSent
{
    public function __construct(
        public readonly Envelope $envelope,
        public readonly string $transportName,
    ) {
    }
}
