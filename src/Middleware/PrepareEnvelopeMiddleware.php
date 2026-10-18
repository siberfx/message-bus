<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;

/**
 * Always the first middleware: stamps the bus name and a unique id.
 */
class PrepareEnvelopeMiddleware implements MiddlewareInterface
{
    public function __construct(protected string $busName)
    {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        if ($envelope->last(BusNameStamp::class) === null) {
            $envelope = $envelope->with(new BusNameStamp($this->busName));
        }

        if ($envelope->last(UniqueIdStamp::class) === null) {
            $envelope = $envelope->with(new UniqueIdStamp());
        }

        return $next($envelope);
    }
}
