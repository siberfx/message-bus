<?php

namespace Siberfx\MessageBus\Contracts;

use Siberfx\MessageBus\Envelope;

/**
 * Transports that can list and look up stored messages (used by the
 * failed message commands).
 */
interface ListableReceiverInterface
{
    /**
     * @return iterable<Envelope>
     */
    public function all(?int $limit = null): iterable;

    public function find(mixed $id): ?Envelope;
}
