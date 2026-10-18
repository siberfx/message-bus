<?php

namespace App\MessageHandlers;

use App\Messages\Queries\GetOrderSummary;
use App\Messages\Queries\ListCustomerOrders;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

/**
 * One read-model class, many queries: put #[AsMessageHandler] on methods.
 */
final class OrderQueries
{
    #[AsMessageHandler(bus: 'query.bus')]
    public function summary(GetOrderSummary $query): array
    {
        $order = Order::with('lines.product', 'customer')->findOrFail($query->orderId);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'customer' => $order->customer->name,
            'lines' => $order->lines->map(fn ($line) => [
                'product' => $line->product->name,
                'quantity' => $line->quantity,
                'total' => $line->quantity * $line->unit_price,
            ])->all(),
            'total' => $order->total,
            'placed_at' => $order->created_at->toIso8601String(),
        ];
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListCustomerOrders $query): LengthAwarePaginator
    {
        // Paginators are Arrayable, so the HTTP endpoint returns the usual
        // {data, current_page, last_page, ...} structure as "result".
        return Order::query()
            ->where('customer_id', $query->customerId)
            ->when($query->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($query->perPage, ['id', 'number', 'status', 'total', 'created_at'], 'page', $query->page);
    }
}
