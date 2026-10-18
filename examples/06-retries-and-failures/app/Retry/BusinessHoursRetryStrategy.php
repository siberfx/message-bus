<?php

namespace App\Retry;

use Illuminate\Support\Carbon;
use Siberfx\MessageBus\Contracts\RetryStrategyInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Retry\MultiplierRetryStrategy;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Throwable;

/**
 * The CRM is only maintained during office hours. Retry quickly during the
 * day; outside office hours, wait until the next business morning.
 */
final class BusinessHoursRetryStrategy implements RetryStrategyInterface
{
    private int $maxRetries;

    /**
     * @param  array<string, mixed>  $config  The merged "retry" config of the transport.
     */
    public function __construct(array $config = [])
    {
        $this->maxRetries = (int) ($config['max_retries'] ?? 10);
    }

    public function isRetryable(Envelope $envelope, ?Throwable $throwable = null): bool
    {
        // Honour Recoverable/Unrecoverable exceptions like the built-in strategy does.
        if ($throwable && MultiplierRetryStrategy::contains($throwable, \Siberfx\MessageBus\Exception\UnrecoverableExceptionInterface::class)) {
            return false;
        }

        return RedeliveryStamp::getRetryCountFromEnvelope($envelope) < $this->maxRetries;
    }

    public function getWaitingTime(Envelope $envelope, ?Throwable $throwable = null): int
    {
        $now = Carbon::now('Europe/Istanbul');

        if ($now->isWeekday() && $now->hour >= 9 && $now->hour < 18) {
            return 60_000; // retry in a minute
        }

        $next = $now->copy()->addDay()->setTime(9, 0);

        while (! $next->isWeekday()) {
            $next->addDay();
        }

        return (int) $now->diffInMilliseconds($next, true);
    }
}
