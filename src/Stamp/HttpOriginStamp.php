<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Added when a message was dispatched from the frontend over HTTP.
 */
final class HttpOriginStamp implements StampInterface
{
    public function __construct(
        public readonly int|string|null $userId = null,
        public readonly ?string $socketId = null,
        public readonly ?string $ip = null,
    ) {
    }
}
