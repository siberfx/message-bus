<?php

namespace Siberfx\MessageBus\Contracts;

use Siberfx\MessageBus\Envelope;
use Throwable;

interface RetryStrategyInterface
{
    public function isRetryable(Envelope $envelope, ?Throwable $throwable = null): bool;

    /**
     * The time to wait before the next retry, in milliseconds.
     */
    public function getWaitingTime(Envelope $envelope, ?Throwable $throwable = null): int;
}
