<?php

namespace App\Messaging\Stamps;

use Siberfx\MessageBus\Stamp\StampInterface;

/**
 * Remembers in which tenant & locale a message was dispatched.
 */
final class TenantStamp implements StampInterface
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $locale,
        public readonly ?int $userId = null,
    ) {
    }
}
