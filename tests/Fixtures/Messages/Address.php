<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

final class Address
{
    public function __construct(
        public readonly string $city,
        public readonly ?string $zip = null,
    ) {
    }
}
