<?php

namespace App\Messages;

final class ChargeCustomer
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $amountInCents,
        public readonly string $currency = 'EUR',
    ) {
    }
}
