<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final class PoisonMessage
{
    public function __construct(public readonly string $reason = 'corrupt')
    {
    }
}
