<?php

namespace App\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Attribute\BroadcastOn;

/**
 * Routed to the "async" transport by its attribute. Once the worker has
 * generated the PDF, the frontend is told on the customer's private channel.
 */
#[AsMessage(transport: 'async', alias: 'invoice.pdf.generate')]
#[BroadcastOn('private-customers.{customerId}', as: 'invoice.ready', withResult: true)]
final class GenerateInvoicePdf
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $customerId,
        public readonly string $locale = 'en',
    ) {
    }
}
