<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

final class OrderPlaced
{
    public function __construct(public readonly int $orderId)
    {
    }
}
