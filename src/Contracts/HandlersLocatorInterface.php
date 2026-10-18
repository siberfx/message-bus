<?php

namespace Siberfx\MessageBus\Contracts;

use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Handler\HandlerDescriptor;

interface HandlersLocatorInterface
{
    /**
     * @return list<HandlerDescriptor>
     */
    public function getHandlers(Envelope $envelope): array;
}
