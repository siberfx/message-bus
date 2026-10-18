<?php

namespace App\Messages\Events;

use App\Contracts\Auditable;
use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(alias: 'order.placed', bus: 'event.bus')]
final class OrderPlaced implements Auditable
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $customerId,
        public readonly int $totalInCents,
    ) {
    }

    public function auditDescription(): string
    {
        return "Order #{$this->orderId} placed by customer #{$this->customerId}";
    }
}
