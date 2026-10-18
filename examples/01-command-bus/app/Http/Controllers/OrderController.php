<?php

namespace App\Http\Controllers;

use App\Messages\PlaceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Stamp\HandledStamp;

class OrderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Validation happens in the bus (ValidationMiddleware + PlaceOrder::rules()),
        // a ValidationException redirects back with errors exactly like $request->validate().
        $envelope = MessageBus::dispatch(new PlaceOrder(
            customerId: $request->user()->customer_id,
            items: $request->input('items', []),
            couponCode: $request->input('coupon_code'),
            note: $request->input('note'),
        ));

        $order = $envelope->last(HandledStamp::class)->result;

        return redirect()->route('orders.show', $order)->with('status', 'Order placed!');
    }
}
