<?php

namespace Siberfx\MessageBus\Contracts;

use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\StampInterface;

interface MessageBusInterface
{
    /**
     * Dispatch the message through the middleware stack.
     *
     * @param  StampInterface[]  $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope;

    /**
     * Dispatch the message and return the result of its single handler.
     *
     * @param  StampInterface[]  $stamps
     */
    public function ask(object $message, array $stamps = []): mixed;

    public function name(): string;
}
