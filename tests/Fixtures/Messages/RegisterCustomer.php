<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use DateTimeImmutable;

final class RegisterCustomer
{
    public function __construct(
        public readonly string $fullName,
        public readonly int $age,
        public readonly Status $status,
        public readonly Address $address,
        public readonly DateTimeImmutable $registeredAt,
        public readonly bool $newsletter = false,
        public readonly array $tags = [],
    ) {
    }
}
