<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(alias: 'greeting.get', exposed: true, bus: 'query.bus')]
final class GetGreeting
{
    public function __construct(public readonly string $name)
    {
    }
}
