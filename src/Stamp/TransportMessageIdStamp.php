<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * The identifier a transport assigned to the stored message.
 */
final class TransportMessageIdStamp implements NonSendableStampInterface
{
    public function __construct(public readonly mixed $id)
    {
    }
}
