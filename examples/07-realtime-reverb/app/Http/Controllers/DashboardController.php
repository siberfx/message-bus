<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Messages\UpdateOrderStatus;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Stamp\BroadcastStamp;
use Siberfx\MessageBus\Stamp\NoBroadcastStamp;

class DashboardController extends Controller
{
    public function ship(Order $order): RedirectResponse
    {
        MessageBus::dispatch(new UpdateOrderStatus($order->id, OrderStatus::Shipped));

        return back();
    }

    /**
     * Bulk import: don't flood clients with one event per order...
     */
    public function bulkShip(): RedirectResponse
    {
        $ids = Order::where('status', OrderStatus::Packed)->pluck('id');

        foreach ($ids as $id) {
            MessageBus::dispatch(new UpdateOrderStatus($id, OrderStatus::Shipped), [new NoBroadcastStamp()]);
        }

        // ...publish a single summary event instead (no message involved).
        MessageBus::publish('presence-shop-floor', 'orders.bulk.shipped', [
            'count' => $ids->count(),
            'by' => auth()->user()->name,
        ]);

        return back();
    }

    /**
     * Broadcast any message on demand, with a custom payload and connection.
     */
    public function flag(Order $order): RedirectResponse
    {
        MessageBus::dispatch(
            new UpdateOrderStatus($order->id, OrderStatus::OnHold, silent: true),
            [new BroadcastStamp(
                channels: ['private-admin', 'private-orders.{orderId}'],
                event: 'order.flagged',
                via: 'reverb',
                payload: ['order_id' => $order->id, 'reason' => request('reason')],
            )],
        );

        return back();
    }
}
