<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Added when a message is moved to the failure transport.
 */
final class SentToFailureTransportStamp implements StampInterface
{
    public function __construct(public readonly string $originalReceiverName)
    {
    }
}
