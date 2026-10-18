<?php

namespace Siberfx\MessageBus\Tests\Fixtures;

use Closure;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;

final class StampingMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        Recorder::record('middleware:before');

        $envelope = $next($envelope);

        Recorder::record('middleware:after');

        return $envelope;
    }
}
