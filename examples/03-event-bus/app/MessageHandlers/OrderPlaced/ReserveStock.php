<?php

namespace App\MessageHandlers\OrderPlaced;

use App\Messages\Events\OrderPlaced;
use App\Services\Inventory;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

/**
 * Runs first: stock must be reserved before anything is communicated.
 */
#[AsMessageHandler(bus: 'event.bus', priority: 100)]
final class ReserveStock
{
    public function __construct(private readonly Inventory $inventory)
    {
    }

    public function __invoke(OrderPlaced $event): void
    {
        $this->inventory->reserveForOrder($event->orderId);
    }
}
