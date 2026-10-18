<?php

namespace Siberfx\MessageBus\Events;

use Siberfx\MessageBus\Envelope;

/**
 * Fired after all handlers of a message ran successfully.
 */
class MessageHandled
{
    public function __construct(
        public readonly Envelope $envelope,
        public readonly ?string $transportName = null,
    ) {
    }
}
