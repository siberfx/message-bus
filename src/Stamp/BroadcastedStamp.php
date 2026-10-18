<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Added after the message was broadcast to realtime channels.
 */
final class BroadcastedStamp implements NonSendableStampInterface
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public readonly string $driver,
        public readonly array $channels,
        public readonly string $event,
    ) {
    }
}
