<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Remembers which bus the message was dispatched on, so workers can route
 * the message back to the same bus when it is consumed.
 */
final class BusNameStamp implements StampInterface
{
    public function __construct(public readonly string $busName)
    {
    }
}
