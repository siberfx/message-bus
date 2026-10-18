<?php

namespace Siberfx\MessageBus\Exception;

/**
 * Thrown when a message cannot be built from an array payload.
 */
class HydrationException extends InvalidArgumentException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
