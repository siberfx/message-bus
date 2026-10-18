<?php

namespace App\MessageHandlers\OrderPlaced;

use App\Messages\Events\OrderPlaced;
use Illuminate\Support\Facades\Http;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

/**
 * Slow HTTP call: route OrderPlaced to an async transport (see example 04)
 * so the request never waits for the warehouse API.
 *
 * Tip: "fromTransport: 'async'" restricts a handler to messages consumed from
 * that transport. Only use it when *every* handler of the message is
 * restricted, otherwise routing to ['sync', 'async'] runs the unrestricted
 * handlers twice (once inline, once in the worker).
 */
#[AsMessageHandler(bus: 'event.bus', priority: -10)]
final class NotifyWarehouse
{
    public function __invoke(OrderPlaced $event): void
    {
        Http::withToken(config('services.warehouse.token'))
            ->post(config('services.warehouse.url').'/orders', ['order_id' => $event->orderId])
            ->throw();
    }
}
