<?php

namespace Siberfx\MessageBus\Stamp;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Delays delivery of the message by the given amount of milliseconds.
 */
final class DelayStamp implements StampInterface
{
    public function __construct(public readonly int $delay)
    {
    }

    public static function seconds(int $seconds): self
    {
        return new self($seconds * 1000);
    }

    public static function delayFor(DateInterval $interval): self
    {
        $now = new DateTimeImmutable();

        return new self(($now->add($interval)->getTimestamp() - $now->getTimestamp()) * 1000);
    }

    public static function delayUntil(DateTimeInterface $dateTime): self
    {
        return new self(max(0, ($dateTime->getTimestamp() - time()) * 1000));
    }
}
