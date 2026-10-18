<?php

namespace App\Messages;

use App\Enums\OrderStatus;
use App\Models\Order;
use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Contracts\ShouldBroadcastLive;

#[AsMessage(alias: 'orders.status.update')]
final class UpdateOrderStatus implements ShouldBroadcastLive
{
    public function __construct(
        public readonly int $orderId,
        public readonly OrderStatus $status,
        public readonly bool $silent = false,
    ) {
    }

    /**
     * The customer's private channel and the shop floor presence channel.
     */
    public function broadcastOn(): array
    {
        $order = Order::findOrFail($this->orderId);

        return ["private-customers.{$order->customer_id}", 'presence-shop-floor'];
    }

    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }

    /**
     * Becomes "data" in the payload (default: all public properties).
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'status' => $this->status->value,
            'label' => $this->status->label(),
        ];
    }

    public function broadcastWhen(): bool
    {
        return ! $this->silent;
    }

    /**
     * Optional: send through another realtime connection (config realtime.connections).
     */
    public function broadcastVia(): ?string
    {
        return null; // default connection
    }

    public function broadcastToOthers(): bool
    {
        return false;
    }
}
