<?php

namespace Siberfx\MessageBus\Stamp;

use DateTimeImmutable;
use Siberfx\MessageBus\Envelope;

/**
 * Tracks how many times a message was retried.
 */
final class RedeliveryStamp implements StampInterface
{
    public readonly DateTimeImmutable $redeliveredAt;

    public function __construct(public readonly int $retryCount, ?DateTimeImmutable $redeliveredAt = null)
    {
        $this->redeliveredAt = $redeliveredAt ?? new DateTimeImmutable();
    }

    public static function getRetryCountFromEnvelope(Envelope $envelope): int
    {
        return $envelope->last(self::class)?->retryCount ?? 0;
    }
}
