<?php

namespace App\MessageHandlers;

use App\Exceptions\CardDeclined;
use App\Exceptions\PaymentGatewayDown;
use App\Messages\ChargeCustomer;
use App\Models\Order;
use App\Services\PaymentGateway;
use App\Services\PaymentGatewayException;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;

#[AsMessageHandler]
final class ChargeCustomerHandler
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    public function __invoke(ChargeCustomer $message, Envelope $envelope): void
    {
        $order = Order::findOrFail($message->orderId);

        if ($order->paid_at !== null) {
            return; // idempotent: a previous attempt already succeeded
        }

        logger()->info('Charging order', [
            'order' => $order->id,
            'attempt' => RedeliveryStamp::getRetryCountFromEnvelope($envelope) + 1,
        ]);

        try {
            $charge = $this->gateway->charge(
                $order->customer->payment_method_id,
                $message->amountInCents,
                $message->currency,
                // the message id is stable across retries: perfect idempotency key
                idempotencyKey: $envelope->last(UniqueIdStamp::class)?->id,
            );
        } catch (PaymentGatewayException $e) {
            throw match ($e->type) {
                'card_declined' => new CardDeclined($e->getMessage(), previous: $e),
                'service_unavailable' => new PaymentGatewayDown($e->getMessage(), previous: $e),
                default => $e, // regular retry policy
            };
        }

        $order->update(['paid_at' => now(), 'charge_id' => $charge->id]);
    }
}
