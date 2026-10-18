<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Added when the message was received from a transport (e.g. by a worker).
 * Messages carrying this stamp are handled instead of being sent again.
 */
final class ReceivedStamp implements NonSendableStampInterface
{
    public function __construct(public readonly string $transportName)
    {
    }
}
