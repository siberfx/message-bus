<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Events\MessageBroadcasted;
use Siberfx\MessageBus\Realtime\BroadcastResolver;
use Siberfx\MessageBus\Realtime\RealtimeManager;
use Siberfx\MessageBus\Stamp\BroadcastedStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Throwable;

/**
 * Broadcasts broadcastable messages to realtime channels after they were
 * handled. Messages sent to an async transport are broadcast by the worker
 * once they are consumed, so clients only hear about work that is done.
 */
class BroadcastMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected BroadcastResolver $resolver,
        protected RealtimeManager $realtime,
        protected ?Dispatcher $events = null,
        protected bool $throw = false,
    ) {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $envelope = $next($envelope);

        if ($envelope->last(BroadcastedStamp::class) !== null || $this->wasOnlySent($envelope)) {
            return $envelope;
        }

        $broadcast = $this->resolver->resolve($envelope);

        if ($broadcast === null || $broadcast['channels'] === []) {
            return $envelope;
        }

        try {
            $connection = $broadcast['via'] ?? $this->realtime->getDefaultConnection();

            $this->realtime->connection($connection)->publish(
                $broadcast['channels'],
                $broadcast['event'],
                $broadcast['payload'],
            );
        } catch (Throwable $e) {
            if ($this->throw) {
                throw $e;
            }

            report($e);

            return $envelope;
        }

        $this->events?->dispatch(new MessageBroadcasted(
            $envelope,
            $connection,
            $broadcast['channels'],
            $broadcast['event'],
            $broadcast['payload'],
        ));

        return $envelope->with(new BroadcastedStamp($connection, $broadcast['channels'], $broadcast['event']));
    }

    protected function wasOnlySent(Envelope $envelope): bool
    {
        return $envelope->last(SentStamp::class) !== null
            && $envelope->last(HandledStamp::class) === null
            && $envelope->last(ReceivedStamp::class) === null;
    }
}
