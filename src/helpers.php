<?php

use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\MessageBusManager;

if (! function_exists('message_bus')) {
    /**
     * Get the message bus manager, or dispatch a message when one is given.
     *
     * @return ($message is null ? MessageBusManager : Envelope)
     */
    function message_bus(?object $message = null, array $stamps = []): MessageBusManager|Envelope
    {
        $manager = app(MessageBusManager::class);

        return $message === null ? $manager : $manager->dispatch($message, $stamps);
    }
}
