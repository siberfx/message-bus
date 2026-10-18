<?php

namespace Siberfx\MessageBus\Exception;

class TransportException extends RuntimeException
{
    public static function unknown(string $name): self
    {
        return new self(sprintf('Message Bus transport "%s" is not defined.', $name));
    }
}
