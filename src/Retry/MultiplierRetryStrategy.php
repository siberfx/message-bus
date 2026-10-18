<?php

namespace Siberfx\MessageBus\Retry;

use Siberfx\MessageBus\Contracts\RetryStrategyInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\Exception\RecoverableExceptionInterface;
use Siberfx\MessageBus\Exception\UnrecoverableExceptionInterface;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Throwable;

/**
 * Retries a message up to $maxRetries times with an exponential delay:
 * delay * multiplier ^ retryCount, optionally capped at $maxDelay.
 */
class MultiplierRetryStrategy implements RetryStrategyInterface
{
    public function __construct(
        protected int $maxRetries = 3,
        protected int $delay = 1000,
        protected float $multiplier = 2,
        protected int $maxDelay = 0,
    ) {
    }

    public function isRetryable(Envelope $envelope, ?Throwable $throwable = null): bool
    {
        if ($throwable !== null) {
            if (self::contains($throwable, UnrecoverableExceptionInterface::class)) {
                return false;
            }

            if (self::contains($throwable, RecoverableExceptionInterface::class)) {
                return true;
            }
        }

        return RedeliveryStamp::getRetryCountFromEnvelope($envelope) < $this->maxRetries;
    }

    public function getWaitingTime(Envelope $envelope, ?Throwable $throwable = null): int
    {
        $retries = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
        $delay = (int) round($this->delay * ($this->multiplier ** $retries));

        return $this->maxDelay > 0 ? min($delay, $this->maxDelay) : $delay;
    }

    /**
     * Whether the throwable, or every exception it wraps, is of the given type.
     */
    public static function contains(Throwable $throwable, string $type): bool
    {
        if ($throwable instanceof HandlerFailedException) {
            $wrapped = $throwable->getWrappedExceptions(recursive: true);

            return $wrapped !== [] && count(array_filter($wrapped, fn (Throwable $e) => $e instanceof $type)) === count($wrapped);
        }

        return $throwable instanceof $type;
    }
}
