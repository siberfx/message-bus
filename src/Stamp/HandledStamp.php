<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Added once per handler that successfully handled the message.
 */
final class HandledStamp implements StampInterface
{
    public function __construct(
        public readonly mixed $result,
        public readonly string $handlerName,
    ) {
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getHandlerName(): string
    {
        return $this->handlerName;
    }
}
