<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Added when the message was sent to a transport.
 */
final class SentStamp implements NonSendableStampInterface
{
    public function __construct(
        public readonly string $senderClass,
        public readonly ?string $senderAlias = null,
    ) {
    }
}
