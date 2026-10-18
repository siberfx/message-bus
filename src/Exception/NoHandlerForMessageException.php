<?php

namespace Siberfx\MessageBus\Exception;

class NoHandlerForMessageException extends LogicException
{
    public static function forMessage(string $messageClass, string $busName): self
    {
        return new self(sprintf(
            'No handler for message "%s" on bus "%s". Register one with #[AsMessageHandler], the "handlers" config key or MessageBus::handle().',
            $messageClass,
            $busName,
        ));
    }
}
