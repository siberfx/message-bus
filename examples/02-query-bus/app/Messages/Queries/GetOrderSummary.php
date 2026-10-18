<?php

namespace App\Messages\Queries;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(alias: 'orders.summary', bus: 'query.bus')]
final class GetOrderSummary
{
    public function __construct(public readonly int $orderId)
    {
    }
}
