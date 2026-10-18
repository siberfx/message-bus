<?php

namespace App\MessageHandlers;

use App\Messages\PlaceOrder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CouponService;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

/**
 * Handlers are resolved from the container, so constructor injection works.
 * The handled message is inferred from the type of the first parameter.
 */
#[AsMessageHandler]
final class PlaceOrderHandler
{
    public function __construct(private readonly CouponService $coupons)
    {
    }

    public function __invoke(PlaceOrder $command): Order
    {
        $customer = Customer::findOrFail($command->customerId);

        $order = $customer->orders()->create([
            'status' => 'pending',
            'note' => $command->note,
        ]);

        foreach ($command->items as $item) {
            $product = Product::where('sku', $item['sku'])->firstOrFail();

            $order->lines()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $product->price,
            ]);
        }

        if ($command->couponCode) {
            $this->coupons->apply($order, $command->couponCode);
        }

        $order->recalculateTotals();

        // The return value is stored in a HandledStamp and returned by the
        // HTTP endpoint as "result" (Eloquent models are converted to arrays).
        return $order->fresh('lines');
    }
}
