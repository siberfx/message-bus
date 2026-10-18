<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Events\MessageSent;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Siberfx\MessageBus\Transport\SendersLocator;
use Siberfx\MessageBus\Transport\Sync\SyncTransport;

/**
 * Sends routed messages to their transports. Messages that are not routed,
 * routed to a "sync" transport, or already received are handled right away.
 */
class SendMessageMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected SendersLocator $senders,
        protected ?Dispatcher $events = null,
    ) {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $next($envelope);
        }

        $sent = false;
        $handleLocally = false;

        foreach ($this->senders->getSenders($envelope) as $alias => $transport) {
            if ($transport instanceof SyncTransport) {
                $handleLocally = true;

                continue;
            }

            $envelope = $transport->send($envelope->with(new SentStamp($transport::class, $alias)));
            $sent = true;

            $this->events?->dispatch(new MessageSent($envelope, $alias));
        }

        if (! $sent || $handleLocally) {
            return $next($envelope);
        }

        return $envelope;
    }
}
