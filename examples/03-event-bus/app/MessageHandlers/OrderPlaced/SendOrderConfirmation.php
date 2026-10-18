<?php

namespace App\MessageHandlers\OrderPlaced;

use App\Mail\OrderConfirmation;
use App\Messages\Events\OrderPlaced;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final class SendOrderConfirmation
{
    public function __invoke(OrderPlaced $event): void
    {
        $order = Order::with('customer')->findOrFail($event->orderId);

        Mail::to($order->customer)->send(new OrderConfirmation($order));
    }
}
