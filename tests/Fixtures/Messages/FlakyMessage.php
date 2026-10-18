<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(transport: 'async')]
final class FlakyMessage
{
    public function __construct(
        public readonly string $key,
        public readonly int $failTimes,
    ) {
    }
}
