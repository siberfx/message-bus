<?php

namespace Siberfx\MessageBus\Events;

use Siberfx\MessageBus\Envelope;
use Throwable;

/**
 * Fired when a consumed message failed. $willRetry tells whether it was
 * scheduled for another attempt or moved to the failure transport.
 */
class MessageFailed
{
    public function __construct(
        public readonly Envelope $envelope,
        public readonly Throwable $exception,
        public readonly string $transportName,
        public readonly bool $willRetry,
    ) {
    }
}
