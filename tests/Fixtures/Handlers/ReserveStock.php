<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Handlers;

use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Tests\Fixtures\Messages\OrderPlaced;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;

#[AsMessageHandler(priority: 10)]
final class ReserveStock
{
    public function __invoke(OrderPlaced $event): void
    {
        Recorder::record('stock:'.$event->orderId);
    }
}
