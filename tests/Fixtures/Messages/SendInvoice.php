<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(alias: 'invoice.send', exposed: true)]
final class SendInvoice
{
    public function __construct(public readonly int $invoiceId)
    {
    }
}
